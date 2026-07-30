<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RoadProximityService
{
    /**
     * Find the nearest road within a given radius using Overpass API.
     *
     * @param  float  $lat
     * @param  float  $lon
     * @param  int  $radius  Radius in meters (e.g., 150)
     * @return int|null Distance to the nearest road in meters, or null if not found/error
     */
    public function getNearestRoadDistance($lat, $lon, $radius = 150)
    {
        // Overpass QL query to find highways (roads) around the point
        $query = "[out:json][timeout:10];
                  way[\"highway\"](around:{$radius},{$lat},{$lon});
                  out geom;";

        try {
            $response = Http::timeout(15)
                ->withHeaders(['User-Agent' => 'AgriSenseApp/1.0'])
                ->get('https://overpass-api.de/api/interpreter', [
                    'data' => $query,
                ]);

            if ($response->successful()) {
                $data = $response->json();

                if (empty($data['elements'])) {
                    return null; // No roads found in radius
                }

                $minDistance = INF;
                $found = false;

                // Calculate actual distance to the closest point of the ways
                foreach ($data['elements'] as $element) {
                    if (isset($element['geometry'])) {
                        foreach ($element['geometry'] as $node) {
                            $dist = $this->haversineGreatCircleDistance(
                                $lat, $lon,
                                $node['lat'], $node['lon']
                            );

                            if ($dist < $minDistance) {
                                $minDistance = $dist;
                                $found = true;
                            }
                        }
                    } elseif (isset($element['lat']) && isset($element['lon'])) {
                        // In case it's a node
                        $dist = $this->haversineGreatCircleDistance(
                            $lat, $lon,
                            $element['lat'], $element['lon']
                        );
                        if ($dist < $minDistance) {
                            $minDistance = $dist;
                            $found = true;
                        }
                    }
                }

                return $found ? (int) round($minDistance) : null;
            } else {
                Log::warning('Overpass API failed with status: '.$response->status());

                return null;
            }
        } catch (\Exception $e) {
            Log::error('Overpass API request failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Calculates the great-circle distance between two points, with
     * the Haversine formula.
     *
     * @return float Distance in meters
     */
    private function haversineGreatCircleDistance($latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthRadius = 6371000)
    {
        $latFrom = deg2rad($latitudeFrom);
        $lonFrom = deg2rad($longitudeFrom);
        $latTo = deg2rad($latitudeTo);
        $lonTo = deg2rad($longitudeTo);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));

        return $angle * $earthRadius;
    }
}

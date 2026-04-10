<div id="map" class="w-full h-96 rounded-lg border border-gray-300 z-0 relative"></div>

@push('scripts')
    <script type="module">
        const landPlots = @json($landPlots ?? []);
        const gardens = @json($gardens ?? []);
        const devices = @json($deviceLocations ?? []);
        const map = L.map('map').setView([-0.7893, 113.9213], 5);
        
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(map);

        const allPolygonsGroup = new L.FeatureGroup();
        devices.forEach(device => {
            if (device.latitude && device.longitude) {
                const marker = L.marker([device.latitude, device.longitude]);
                marker.bindPopup(`<b> Perangkat:</b> ${device.device_code}`);
                allPolygonsGroup.addLayer(marker);
            }
        });
        landPlots.forEach(lahan => {
            if (lahan.polygon) {
                try {
                    const geometry = typeof lahan.polygon === 'string' ? JSON.parse(lahan.polygon) : lahan.polygon;
                    const layerLahan = L.geoJSON(geometry, {
                        style: { color: '#085086ff', weight: 2, fillOpacity: 0.4 }
                    });
                    layerLahan.bindPopup(`<b>Lahan:</b> ${lahan.plot_name}`);
                    allPolygonsGroup.addLayer(layerLahan);
                } catch (e) {
                    console.error("Gagal load Lahan:", e);
                }
            }
        });

        gardens.forEach(kebun => {
            if (kebun.polygon) {
                try {
                    const geometry = typeof kebun.polygon === 'string' ? JSON.parse(kebun.polygon) : kebun.polygon;
                    const layerKebun = L.geoJSON(geometry, {
                        style: { color: '#0fed85ff', weight: 2, fillOpacity: 0.5 }
                    });
                    layerKebun.bindPopup(`<b>Kebun:</b> ${kebun.garden_name}`);
                    allPolygonsGroup.addLayer(layerKebun);
                } catch (e) {
                    console.error("Gagal load Kebun:", e);
                }
            }
        });

        map.addLayer(allPolygonsGroup);

        if (allPolygonsGroup.getLayers().length > 0) {
            map.fitBounds(allPolygonsGroup.getBounds());
        }

        setTimeout(function(){ map.invalidateSize() }, 1000);
    </script>
@endpush

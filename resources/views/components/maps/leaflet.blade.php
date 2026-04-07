<div id="map" class="w-full h-96 rounded-lg border border-gray-300 z-0 relative"></div>

@push('scripts')
    <script type="module">

        const map = L.map('map').setView([-6.8863, 107.6151], 15);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(map);

        const marker = L.marker([-6.8863, 107.6151]).addTo(map);
        marker.bindPopup("UNIKOM").openPopup();
        setTimeout(function(){ map.invalidateSize()}, 500);
    </script>
@endpush

@push('styles')
<style>

    .legend-toggle-btn {
        position: absolute;
        bottom: 20px;
        right: 10px;
        z-index: 1001;
        background: white;
        padding: 10px 15px;
        border-radius: 50px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        cursor: pointer;
        font-weight: bold;
        font-size: 13px;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s ease; 
        border: 1px solid #eee;
    }

    .legend-toggle-btn:active {
        transform: scale(0.9);
        background-color: #f0f0f0;
    }

    .legend-toggle-btn:hover {
        background-color: #f9f9f9;
        box-shadow: 0 6px 20px rgba(0,0,0,0.2);
    }

    .info.legend {
        transition: opacity 0.3s ease, transform 0.3s ease;
    }

    .info.legend.hidden {
        display: none;
        opacity: 0;
        transform: translateY(10px);
    }

    .info.legend {
        background: white; padding: 10px; line-height: 18px; color: #555;
        border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.2);
        margin-bottom: 45px !important; 
    }
    .legend i {
        width: 18px; height: 18px; float: left; margin-right: 8px;
        opacity: 0.7; border-radius: 3px; border: 1px solid rgba(0,0,0,0.1);
    }
    .marker-online {
        background-color: #10b981; border: 2px solid white; border-radius: 50%;
        box-shadow: 0 0 10px rgba(0,0,0,0.3);
    }
    .marker-offline {
        background-color: #ef4444; border: 2px solid white; border-radius: 50%;
        box-shadow: 0 0 10px rgba(0,0,0,0.3);
    }
    .pulse-online {
        animation: pulse-green 2s infinite;
    }
    @keyframes pulse-green {
        0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
        70% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
        100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
    }
</style>
@endpush

<div class="relative">
    <!-- Tombol Toggle Legenda -->
    <div id="btn-toggle-legend" class="legend-toggle-btn" style="color: #ef4444;">
        <i class="fa fa-times text-red-500"></i> Tutup Legenda
    </div>
    
    <!-- Peta -->
    <div id="map" class="w-full h-96 rounded-xl border border-gray-300 z-0 relative"></div>
</div>
    
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
        let layersList = []; 

        devices.forEach(device => {
            if (device.latitude && device.longitude) {
                const status = (device.device_status || 'offline').toLowerCase();
                const iconClass = status === 'online' ? 'marker-online pulse-online' : 'marker-offline';
                
                const customIcon = L.divIcon({
                    className: iconClass,
                    iconSize: [16, 16],
                    iconAnchor: [8, 8]
                });

                const marker = L.marker([device.latitude, device.longitude], { icon: customIcon });
                
                window.deviceMarkers = window.deviceMarkers || {};
                window.deviceMarkers[device.id] = marker;

                const statusColor = status === 'online' ? '#10b981' : '#ef4444';
                
                const popupContent = `
                    <div style="min-width: 180px;">
                        <h4 style="margin: 0 0 8px 0; font-weight: bold; border-bottom: 1px solid #eee; padding-bottom: 5px;">Perangkat IoT</h4>
                        <div style="font-size: 13px; margin-bottom: 4px;"><b>Kode Perangat:</b> ${device.device_code}</div>
                        <div style="font-size: 13px; margin-bottom: 4px;"><b>Lahan:</b> ${device.land_plot ? device.land_plot.plot_name : '-'}</div>
                        <div style="font-size: 13px; margin-bottom: 4px;"><b>Kebun:</b> ${device.garden ? device.garden.garden_name : '-'}</div>
                        <div style="font-size: 13px; margin-bottom: 4px;"><b>Tanaman:</b> ${device.garden ? device.garden.plant_types : '-'}</div>
                        <div style="font-size: 13px; margin-bottom: 8px;"><b>Status:</b> 
                            <span style="color: ${statusColor}; font-weight: bold;">${status.toUpperCase()}</span>
                        </div>
                        ${device.last_seen_at ? `<div style="font-size: 11px; color: #666; font-style: italic;">Terakhir: ${new Date(device.last_seen_at).toLocaleString('id-ID')}</div>` : ''}
                        <div style="margin-top: 10px; padding-top: 8px; border-top: 1px solid #eee; text-align: center;">
                            <a href="/device/${device.id}" style="color: #2563eb; font-weight: bold; text-decoration: none;">Lihat Detail &raquo;</a>
                        </div>
                    </div>
                `;
                marker.bindPopup(popupContent);
                allPolygonsGroup.addLayer(marker);
                layersList.push({ type: 'device', item: device, layer: marker });
            }
        });

        landPlots.forEach(lahan => {
            if (lahan.polygon) {
                try {
                    const geometry = typeof lahan.polygon === 'string' ? JSON.parse(lahan.polygon) : lahan.polygon;
                    const layerLahan = L.geoJSON(geometry, {
                        style: { color: '#085086ff', weight: 2, fillOpacity: 0.4 }
                    });
                    
                    let popupContent = `
                        <div style="min-width: 200px; font-size: 13px;">
                            <h4 style="margin: 0 0 8px 0; font-weight: bold; border-bottom: 1px solid #eee; padding-bottom: 5px; color: #085086;">Informasi Lahan</h4>
                            <div style="margin-bottom: 4px;"><b>Nama:</b> ${lahan.plot_name || '-'}</div>
                            <div style="margin-bottom: 4px;"><b>Kode Lahan:</b> ${lahan.plot_code || '-'}</div>
                            <div style="margin-bottom: 4px;"><b>Luas:</b> ${lahan.area_hectare || '0'} Ha</div>
                            <div style="margin-bottom: 8px;"><b>Tanaman:</b> ${lahan.plant_types || '-'}</div>
                            <div style="padding-top: 8px; border-top: 1px solid #eee; text-align: center;">
                                <a href="/land-plot/${lahan.id}" style="color: #2563eb; font-weight: bold; text-decoration: none;">Lihat Detail &raquo;</a>
                            </div>
                        </div>
                    `;
                    
                    layerLahan.bindPopup(popupContent);
                    allPolygonsGroup.addLayer(layerLahan);
                    layersList.push({ type: 'land', item: lahan, layer: layerLahan });
                } catch (e) { console.error("Gagal load Lahan:", e); }
            }
        });

        gardens.forEach(kebun => {
            if (kebun.polygon) {
                try {
                    const geometry = typeof kebun.polygon === 'string' ? JSON.parse(kebun.polygon) : kebun.polygon;
                    const layerKebun = L.geoJSON(geometry, {
                        style: { color: '#0fed85ff', weight: 2, fillOpacity: 0.5 }
                    });

                    let popupContent = `
                        <div style="min-width: 200px; font-size: 13px;">
                            <h4 style="margin: 0 0 8px 0; font-weight: bold; border-bottom: 1px solid #eee; padding-bottom: 5px; color: #10b981;">Informasi Kebun</h4>
                            <div style="margin-bottom: 4px;"><b>Nama:</b> ${kebun.garden_name || '-'}</div>
                            <div style="margin-bottom: 4px;"><b>Kode Kebun:</b> ${kebun.garden_code || '-'}</div>
                            <div style="margin-bottom: 4px;"><b>Luas:</b> ${kebun.area_hectare || '0'} Ha</div>
                            <div style="margin-bottom: 4px;"><b>Jenis Tanah:</b> ${kebun.soil_type || '-'}</div>
                            <div style="margin-bottom: 8px;"><b>Tanaman:</b> ${kebun.plant_types || '-'}</div>
                            <div style="padding-top: 8px; border-top: 1px solid #eee; text-align: center;">
                                <a href="/garden/${kebun.id}" style="color: #2563eb; font-weight: bold; text-decoration: none;">Lihat Detail &raquo;</a>
                            </div>
                        </div>
                    `;

                    layerKebun.bindPopup(popupContent);
                    allPolygonsGroup.addLayer(layerKebun);
                    layersList.push({ type: 'garden', item: kebun, layer: layerKebun });
                } catch (e) { console.error("Gagal load Kebun:", e); }
            }
        });

        // Hierarchical Search Logic
        document.getElementById('btn-search').addEventListener('click', function() {
            const query = document.getElementById('map-search').value.toLowerCase().trim();
            if(!query) return;

            let results = [];
            
            layersList.forEach(entry => {
                let matchType = 0; 
                let searchableStrings = [];

                if(entry.type === 'land') {
                    searchableStrings = [entry.item.plot_name, entry.item.plot_code, entry.item.owner_name, entry.item.address, entry.item.plant_types];
                } else if(entry.type === 'garden') {
                    searchableStrings = [entry.item.garden_name, entry.item.garden_code, entry.item.soil_type, entry.item.plant_types];
                } else if(entry.type === 'device') {
                    searchableStrings = [entry.item.device_code];
                }

                searchableStrings.filter(s => s).forEach(str => {
                    const lowerStr = str.toLowerCase();
                    if (lowerStr === query) {
                        matchType = 2; 
                    } else if (matchType < 2 && lowerStr.includes(query)) {
                        matchType = 1; 
                    }
                });

                if (matchType > 0) {
                    results.push({ ...entry, matchType });
                }
            });

            const typePriority = { 'land': 1, 'garden': 2, 'device': 3 };

            results.sort((a, b) => {
                if (a.matchType !== b.matchType) {
                    return b.matchType - a.matchType;
                }

                return typePriority[a.type] - typePriority[b.type];
            });

            const match = results[0];

            if (match) {
                if(match.type === 'device') {
                    map.setView(match.layer.getLatLng(), 18);
                } else {
                    map.fitBounds(match.layer.getBounds(), { padding: [50, 50] });
                }
                match.layer.openPopup();
                return;
            }

            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&countrycodes=id&limit=1`)
                .then(res => res.json())
                .then(data => {
                    if(data.length > 0) {
                        map.setView([data[0].lat, data[0].lon], 15);
                        L.popup().setLatLng([data[0].lat, data[0].lon]).setContent(`Hasil pencarian: ${data[0].display_name}`).openOn(map);
                    } else {
                        alert('Pencarian tidak ditemukan di data lokal maupun peta utama.');
                    }
                });
        });

        document.getElementById('map-search').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('btn-search').click();
            }
        });

        map.addLayer(allPolygonsGroup);

        if (allPolygonsGroup.getLayers().length > 0) {
            map.fitBounds(allPolygonsGroup.getBounds(), { padding: [30, 30] });
        }

        setTimeout(function(){ map.invalidateSize() }, 1000);
        
        const legend = L.control({position: 'bottomright'});
        legend.onAdd = function (map) {
            const div = L.DomUtil.create('div', 'info legend');
            div.id = 'map-legend-box';
            const onlineCount = devices.filter(d => (d.status || d.device_status) === 'online').length;
            const offlineCount = devices.length - onlineCount;
            div.innerHTML = '<h4 style="margin: 0 0 8px 0; font-weight: bold; font-size: 14px;">Legenda & Statistik</h4>';
            div.innerHTML += `<i style="background: #085086ff"></i> Lahan (${landPlots.length}) <br>`;
            div.innerHTML += `<i style="background: #0fed85ff"></i> Kebun (${gardens.length}) <br>`;
            div.innerHTML += `<i style="background: #10b981; border-radius: 50%;"></i> Online (${onlineCount}) <br>`;
            div.innerHTML += `<i style="background: #ef4444; border-radius: 50%;"></i> Offline (${offlineCount}) <br>`;
            
            return div;
        };
        legend.addTo(map);
        const btnToggle = document.getElementById('btn-toggle-legend');
        btnToggle.addEventListener('click', function() {
            const legendBox = document.getElementById('map-legend-box');
            if (legendBox) {
                const isHidden = legendBox.classList.toggle('hidden');
                
                if (isHidden) {
                    btnToggle.innerHTML = '<i class="fa fa-layer-group"></i> Tampilkan Legenda';
                    btnToggle.style.color = '#333';
                } else {
                    btnToggle.innerHTML = '<i class="fa fa-times text-red-500"></i> Tutup Legenda';
                    btnToggle.style.color = '#ef4444'; 
                }
            }
        });

        window.updateMarkerStatus = function(deviceId, status) {
            if (!window.deviceMarkers || !window.deviceMarkers[deviceId]) return;
            
            const marker = window.deviceMarkers[deviceId];
            const iconClass = status === 'online' ? 'marker-online pulse-online' : 'marker-offline';
            const newIcon = L.divIcon({
                className: iconClass,
                iconSize: [16, 16],
                iconAnchor: [8, 8]
            });
            
            marker.setIcon(newIcon);
            const popup = marker.getPopup();
            if (popup) {
                const statusColor = status === 'online' ? '#10b981' : '#ef4444';
                const currentContent = popup.getContent();
                if (typeof currentContent === 'string') {
                    const updatedContent = currentContent.replace(/Status:<\/b>\s*<span style="color: [^;]+; font-weight: bold;">[^<]+<\/span>/i, 
                        `Status:</b> <span style="color: ${statusColor}; font-weight: bold;">${status.toUpperCase()}</span>`);
                    marker.setPopupContent(updatedContent);
                }
            }
        };

    </script>
@endpush

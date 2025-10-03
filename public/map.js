document.addEventListener('DOMContentLoaded', () => {
    const photos = (window.PHOTOS || []).filter(photo => {
        const lat = parseFloat(photo.latitude);
        const lng = parseFloat(photo.longitude);
        return Number.isFinite(lat) && Number.isFinite(lng);
    });
    const map = L.map('map').setView([20, 0], 2);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> Contributors',
        maxZoom: 18,
    }).addTo(map);

    const markers = [];
    photos.forEach(photo => {
        const lat = parseFloat(photo.latitude);
        const lng = parseFloat(photo.longitude);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
            return;
        }
        const marker = L.marker([lat, lng]).addTo(map);
        const popupContent = `
            <div class="popup">
                <strong>${photo.title || 'Ohne Titel'}</strong><br>
                ${photo.description ? `<em>${photo.description}</em><br>` : ''}
                ${photo.taken_at ? `Aufgenommen am: ${photo.taken_at}<br>` : ''}
            </div>
        `;
        marker.bindPopup(popupContent);
        markers.push(marker);
    });

    if (markers.length > 0) {
        const group = L.featureGroup(markers);
        map.fitBounds(group.getBounds().pad(0.3));
    }
});

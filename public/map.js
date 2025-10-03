document.addEventListener('DOMContentLoaded', () => {
    const photos = (window.PHOTOS || []).filter(photo => {
        const lat = parseFloat(photo.latitude);
        const lng = parseFloat(photo.longitude);
        return Number.isFinite(lat) && Number.isFinite(lng);
    });
    const defaultView = { center: [20, 0], zoom: 2 };
    const map = L.map('map').setView(defaultView.center, defaultView.zoom);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> Contributors',
        maxZoom: 18,
    }).addTo(map);

    const markers = [];
    let bounds = null;
    photos.forEach(photo => {
        const lat = parseFloat(photo.latitude);
        const lng = parseFloat(photo.longitude);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
            return;
        }
        const marker = L.marker([lat, lng]).addTo(map);
        const categories = Array.isArray(photo.categories) && photo.categories.length > 0
            ? `<div class="popup-categories">${photo.categories.join(', ')}</div>`
            : '';
        const image = photo.image_url
            ? `<img src="${photo.image_url}" alt="${photo.title || 'Foto'}" class="popup-image">`
            : '';
        const popupContent = `
            <div class="popup">
                ${image}
                <strong>${photo.title || 'Ohne Titel'}</strong><br>
                ${photo.description ? `<em>${photo.description}</em><br>` : ''}
                ${categories}
                ${photo.taken_at ? `<span class="popup-date">Aufgenommen am: ${photo.taken_at}</span><br>` : ''}
            </div>
        `;
        marker.bindPopup(popupContent);
        markers.push(marker);
    });

    if (markers.length > 0) {
        const group = L.featureGroup(markers);
        bounds = group.getBounds().pad(0.3);
        map.fitBounds(bounds);
    }

    const homeControl = L.control({ position: 'topleft' });
    homeControl.onAdd = () => {
        const container = L.DomUtil.create('div', 'leaflet-bar home-control');
        const button = L.DomUtil.create('button', '', container);
        button.type = 'button';
        button.textContent = 'Home';
        L.DomEvent.on(button, 'click', (event) => {
            L.DomEvent.stop(event);
            if (bounds) {
                map.fitBounds(bounds);
            } else {
                map.setView(defaultView.center, defaultView.zoom);
            }
        });
        return container;
    };
    homeControl.addTo(map);
});

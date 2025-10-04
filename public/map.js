document.addEventListener('DOMContentLoaded', () => {
    const allPhotos = window.PHOTOS || [];
    const photoIndex = new Map();
    allPhotos.forEach(photo => {
        const id = Number(photo.id);
        if (Number.isFinite(id)) {
            photoIndex.set(id, photo);
        }
    });

    const photos = allPhotos.filter(photo => {
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

    const detail = document.querySelector('[data-photo-detail]');
    const detailEmpty = detail ? detail.querySelector('[data-photo-detail-empty]') : null;
    const detailBody = detail ? detail.querySelector('[data-photo-detail-body]') : null;
    const detailImage = detail ? detail.querySelector('[data-photo-detail-image]') : null;
    const detailTitle = detail ? detail.querySelector('[data-photo-detail-title]') : null;
    const detailDescription = detail ? detail.querySelector('[data-photo-detail-description]') : null;
    const detailCategories = detail ? detail.querySelector('[data-photo-detail-categories]') : null;
    const detailCategoriesRow = detail ? detail.querySelector('[data-photo-detail-categories-row]') : null;
    const detailCoordinates = detail ? detail.querySelector('[data-photo-detail-coordinates]') : null;
    const detailCoordinatesRow = detail ? detail.querySelector('[data-photo-detail-coordinates-row]') : null;
    const detailDate = detail ? detail.querySelector('[data-photo-detail-date]') : null;
    const detailDateRow = detail ? detail.querySelector('[data-photo-detail-date-row]') : null;
    const detailClose = detail ? detail.querySelector('[data-photo-detail-close]') : null;
    let activeCard = null;
    let suppressScroll = false;

    const highlightCard = (photoId) => {
        if (activeCard) {
            activeCard.classList.remove('active');
            activeCard = null;
        }
        if (!Number.isFinite(photoId)) {
            return;
        }
        const card = document.querySelector(`[data-photo-card][data-photo-id="${photoId}"]`);
        if (card) {
            card.classList.add('active');
            activeCard = card;
            if (!suppressScroll) {
                card.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
            }
        }
    };

    const showDetail = (photo) => {
        if (!detail || !photo) {
            return;
        }
        if (detailEmpty) {
            detailEmpty.classList.add('hidden');
        }
        if (detailBody) {
            detailBody.classList.remove('hidden');
        }
        if (detailImage) {
            const imageUrl = photo.original_image_url || photo.image_url;
            detailImage.src = imageUrl;
            detailImage.alt = photo.title || 'Ohne Titel';
        }
        if (detailTitle) {
            detailTitle.textContent = photo.title || 'Ohne Titel';
        }
        if (detailDescription) {
            if (photo.description) {
                detailDescription.textContent = photo.description;
                detailDescription.classList.remove('hidden');
            } else {
                detailDescription.textContent = '';
                detailDescription.classList.add('hidden');
            }
        }
        if (detailCategories && detailCategoriesRow) {
            if (Array.isArray(photo.categories) && photo.categories.length > 0) {
                detailCategoriesRow.classList.remove('hidden');
                detailCategories.textContent = photo.categories.join(', ');
            } else {
                detailCategoriesRow.classList.add('hidden');
                detailCategories.textContent = '';
            }
        }
        if (detailCoordinates && detailCoordinatesRow) {
            const lat = parseFloat(photo.latitude);
            const lng = parseFloat(photo.longitude);
            if (Number.isFinite(lat) && Number.isFinite(lng)) {
                detailCoordinatesRow.classList.remove('hidden');
                detailCoordinates.textContent = `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
            } else {
                detailCoordinatesRow.classList.add('hidden');
                detailCoordinates.textContent = '';
            }
        }
        if (detailDate && detailDateRow) {
            if (photo.taken_at) {
                detailDateRow.classList.remove('hidden');
                detailDate.textContent = photo.taken_at;
            } else {
                detailDateRow.classList.add('hidden');
                detailDate.textContent = '';
            }
        }
        detail.classList.add('active');
        highlightCard(Number(photo.id));
    };

    const hideDetail = () => {
        if (!detail) {
            return;
        }
        if (detailBody) {
            detailBody.classList.add('hidden');
        }
        if (detailEmpty) {
            detailEmpty.classList.remove('hidden');
        }
        if (detailImage) {
            detailImage.src = '';
            detailImage.alt = '';
        }
        if (detailDescription) {
            detailDescription.textContent = '';
            detailDescription.classList.add('hidden');
        }
        detail.classList.remove('active');
        highlightCard(NaN);
    };

    if (detailClose) {
        detailClose.addEventListener('click', () => {
            hideDetail();
        });
    }

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
        marker.on('click', () => {
            showDetail(photo);
        });
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

    const openPhotoFromCard = (card) => {
        if (!card) {
            return;
        }
        const id = Number(card.getAttribute('data-photo-id'));
        if (!Number.isFinite(id)) {
            return;
        }
        const photo = photoIndex.get(id);
        if (photo) {
            suppressScroll = true;
            showDetail(photo);
            suppressScroll = false;
        }
    };

    document.querySelectorAll('[data-photo-trigger]').forEach(trigger => {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            const card = trigger.closest('[data-photo-card]');
            openPhotoFromCard(card);
        });
    });

    document.querySelectorAll('[data-photo-card]').forEach(card => {
        card.addEventListener('click', (event) => {
            if (event.target.closest('[data-photo-trigger]')) {
                return;
            }
            openPhotoFromCard(card);
        });
        card.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openPhotoFromCard(card);
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            hideDetail();
        }
    });
});

document.addEventListener('DOMContentLoaded', () => {
    const allPhotos = window.PHOTOS || [];
    const photoIndex = new Map();
    allPhotos.forEach(photo => {
        const id = Number(photo.id);
        if (Number.isFinite(id)) {
            photoIndex.set(id, photo);
        }
    });

    const sanitizeColor = (input) => {
        if (typeof input !== 'string') {
            return '#3388FF';
        }
        const trimmed = input.trim();
        const match = trimmed.match(/^#([0-9a-fA-F]{6})$/);
        if (!match) {
            return '#3388FF';
        }
        return `#${match[1].toUpperCase()}`;
    };

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => {
        switch (char) {
            case '&':
                return '&amp;';
            case '<':
                return '&lt;';
            case '>':
                return '&gt;';
            case '"':
                return '&quot;';
            case "'":
                return '&#39;';
            default:
                return char;
        }
    });

    const renderCategoryBadges = (categories) => {
        if (!Array.isArray(categories) || categories.length === 0) {
            return '';
        }
        return categories.map((category) => {
            const name = escapeHtml(category?.name ?? '');
            const color = sanitizeColor(category?.color);
            return `<span class="category-pill" style="--category-color: ${color}">${name}</span>`;
        }).join(' ');
    };

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
            if (Array.isArray(photo.category_details) && photo.category_details.length > 0) {
                detailCategoriesRow.classList.remove('hidden');
                detailCategories.innerHTML = renderCategoryBadges(photo.category_details);
            } else {
                detailCategoriesRow.classList.add('hidden');
                detailCategories.innerHTML = '';
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
        if (detailCategories) {
            detailCategories.innerHTML = '';
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
        const markerColor = sanitizeColor(photo.primary_color || (photo.category_details && photo.category_details[0]?.color));
        const marker = L.circleMarker([lat, lng], {
            radius: 8,
            color: markerColor,
            fillColor: markerColor,
            fillOpacity: 0.9,
            weight: 2,
        }).addTo(map);
        const categories = Array.isArray(photo.category_details) && photo.category_details.length > 0
            ? `<div class="popup-categories">${renderCategoryBadges(photo.category_details)}</div>`
            : '';
        const popupTitle = escapeHtml(photo.title || 'Ohne Titel');
        const popupDescription = photo.description ? `<em>${escapeHtml(photo.description)}</em><br>` : '';
        const popupTaken = photo.taken_at ? `<span class="popup-date">Aufgenommen am: ${escapeHtml(photo.taken_at)}</span><br>` : '';
        const imageUrl = typeof photo.image_url === 'string' ? escapeHtml(photo.image_url) : '';
        const image = imageUrl
            ? `<img src="${imageUrl}" alt="${popupTitle}" class="popup-image">`
            : '';
        const popupContent = `
            <div class="popup">
                ${image}
                <strong>${popupTitle}</strong><br>
                ${popupDescription}
                ${categories}
                ${popupTaken}
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

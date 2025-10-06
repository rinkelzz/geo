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

    const detail = document.querySelector('[data-photo-detail]');
    const detailEmpty = detail ? detail.querySelector('[data-photo-detail-empty]') : null;
    const detailBody = detail ? detail.querySelector('[data-photo-detail-body]') : null;
    const detailImage = detail ? detail.querySelector('[data-photo-detail-image]') : null;
    const detailTitle = detail ? detail.querySelector('[data-photo-detail-title]') : null;
    const detailIdInput = detail ? detail.querySelector('[data-photo-detail-id]') : null;
    const detailTitleInput = detail ? detail.querySelector('[data-photo-detail-title-input]') : null;
    const detailDescriptionInput = detail ? detail.querySelector('[data-photo-detail-description-input]') : null;
    const detailLatitudeInput = detail ? detail.querySelector('[data-photo-detail-latitude]') : null;
    const detailLongitudeInput = detail ? detail.querySelector('[data-photo-detail-longitude]') : null;
    const detailTakenInput = detail ? detail.querySelector('[data-photo-detail-taken]') : null;
    const detailCategoryInputs = detail ? Array.from(detail.querySelectorAll('[data-category-checkbox]')) : [];
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
        if (detailIdInput) {
            detailIdInput.value = photo.id;
        }
        if (detailTitleInput) {
            detailTitleInput.value = photo.title || '';
        }
        if (detailDescriptionInput) {
            detailDescriptionInput.value = photo.description || '';
        }
        if (detailLatitudeInput) {
            const lat = parseFloat(photo.latitude);
            detailLatitudeInput.value = Number.isFinite(lat) ? lat.toFixed(6) : '';
        }
        if (detailLongitudeInput) {
            const lng = parseFloat(photo.longitude);
            detailLongitudeInput.value = Number.isFinite(lng) ? lng.toFixed(6) : '';
        }
        if (detailTakenInput) {
            if (photo.taken_at) {
                const formatted = photo.taken_at.replace(' ', 'T').slice(0, 16);
                detailTakenInput.value = formatted;
            } else {
                detailTakenInput.value = '';
            }
        }
        if (detailCategoryInputs.length > 0) {
            const selectedCategories = Array.isArray(photo.category_ids)
                ? photo.category_ids.map((id) => Number(id))
                : [];
            detailCategoryInputs.forEach((input) => {
                const value = Number(input.value);
                input.checked = selectedCategories.includes(value);
            });
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
        if (detailTitle) {
            detailTitle.textContent = 'Details';
        }
        if (detailIdInput) {
            detailIdInput.value = '';
        }
        if (detailTitleInput) {
            detailTitleInput.value = '';
        }
        if (detailDescriptionInput) {
            detailDescriptionInput.value = '';
        }
        if (detailLatitudeInput) {
            detailLatitudeInput.value = '';
        }
        if (detailLongitudeInput) {
            detailLongitudeInput.value = '';
        }
        if (detailTakenInput) {
            detailTakenInput.value = '';
        }
        if (detailCategoryInputs.length > 0) {
            detailCategoryInputs.forEach((input) => {
                input.checked = false;
            });
        }
        detail.classList.remove('active');
        highlightCard(NaN);
    };

    if (detailClose) {
        detailClose.addEventListener('click', () => {
            hideDetail();
        });
    }

    const categoryFilterInputs = Array.from(document.querySelectorAll('[data-category-filter]'));
    const categoryResetButton = document.querySelector('[data-category-filter-reset]');
    const hiddenCategoryIds = new Set();
    let hideUncategorized = false;
    const markerEntries = [];

    const mapElement = document.getElementById('map');
    const hasLeaflet = typeof L !== 'undefined';
    const defaultView = { center: [20, 0], zoom: 2 };
    let map = null;
    let bounds = null;
    let initialBounds = null;
    let hasVisibleMarkers = false;

    const computeVisibleBounds = () => {
        if (!map || !hasLeaflet) {
            return null;
        }
        const visibleMarkers = markerEntries
            .filter((entry) => map.hasLayer(entry.marker))
            .map((entry) => entry.marker);
        if (visibleMarkers.length === 0) {
            return null;
        }
        return L.featureGroup(visibleMarkers).getBounds().pad(0.3);
    };

    const applyCategoryFilters = () => {
        if (!map) {
            return;
        }
        markerEntries.forEach((entry) => {
            const hasCategories = entry.categoryIds.length > 0;
            const shouldHide = hasCategories
                ? entry.categoryIds.some((id) => hiddenCategoryIds.has(id))
                : hideUncategorized;
            const isVisible = map.hasLayer(entry.marker);
            if (shouldHide && isVisible) {
                entry.marker.closePopup();
                entry.marker.removeFrom(map);
            } else if (!shouldHide && !isVisible) {
                entry.marker.addTo(map);
            }
        });
        hasVisibleMarkers = markerEntries.some((entry) => map.hasLayer(entry.marker));
        const updatedBounds = computeVisibleBounds();
        bounds = updatedBounds || initialBounds;
    };

    const syncFilterStateFromInput = (input) => {
        if (!input) {
            return;
        }
        const key = input.getAttribute('data-category-filter');
        if (key === 'none') {
            hideUncategorized = !input.checked;
            return;
        }
        const categoryId = Number(key);
        if (!Number.isFinite(categoryId)) {
            return;
        }
        if (input.checked) {
            hiddenCategoryIds.delete(categoryId);
        } else {
            hiddenCategoryIds.add(categoryId);
        }
    };

    categoryFilterInputs.forEach((input) => {
        input.addEventListener('change', () => {
            syncFilterStateFromInput(input);
            applyCategoryFilters();
        });
    });

    if (categoryResetButton) {
        categoryResetButton.addEventListener('click', () => {
            hiddenCategoryIds.clear();
            hideUncategorized = false;
            categoryFilterInputs.forEach((input) => {
                input.checked = true;
            });
            applyCategoryFilters();
        });
    }

    if (mapElement && hasLeaflet) {
        map = L.map(mapElement).setView(defaultView.center, defaultView.zoom);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> Contributors',
            maxZoom: 18,
        }).addTo(map);

        const photosWithCoordinates = allPhotos.filter(photo => {
            const lat = parseFloat(photo.latitude);
            const lng = parseFloat(photo.longitude);
            return Number.isFinite(lat) && Number.isFinite(lng);
        });

        const markers = [];
        photosWithCoordinates.forEach(photo => {
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
            const popupTaken = photo.taken_at ? `<span class="popup-date">Aufgenommen am: ${escapeHtml(photo.taken_at)}</span><br>`
                : '';
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
            const categoryIds = Array.isArray(photo.category_ids)
                ? photo.category_ids
                    .map((id) => Number(id))
                    .filter((value) => Number.isFinite(value))
                : [];
            markerEntries.push({
                marker,
                categoryIds,
            });
        });

        if (markers.length > 0) {
            const group = L.featureGroup(markers);
            bounds = group.getBounds().pad(0.3);
            map.fitBounds(bounds);
            initialBounds = bounds;
        }

        const homeControl = L.control({ position: 'topleft' });
        homeControl.onAdd = () => {
            const container = L.DomUtil.create('div', 'leaflet-bar home-control');
            const button = L.DomUtil.create('button', '', container);
            button.type = 'button';
            button.textContent = 'Home';
            L.DomEvent.on(button, 'click', (event) => {
                L.DomEvent.stop(event);
                if (hasVisibleMarkers && bounds) {
                    map.fitBounds(bounds);
                } else if (initialBounds) {
                    map.fitBounds(initialBounds);
                } else {
                    map.setView(defaultView.center, defaultView.zoom);
                }
            });
            return container;
        };
        homeControl.addTo(map);

        applyCategoryFilters();

        setTimeout(() => {
            map.invalidateSize();
            if (hasVisibleMarkers && bounds) {
                map.fitBounds(bounds);
            } else if (initialBounds) {
                map.fitBounds(initialBounds);
            } else {
                map.setView(defaultView.center, defaultView.zoom);
            }
        }, 200);
    }

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
            if (window.GeoPhotothek && typeof window.GeoPhotothek.setActivePanel === 'function') {
                window.GeoPhotothek.setActivePanel('dashboard');
            }
            suppressScroll = true;
            showDetail(photo);
            suppressScroll = false;
        }
    };

    const ignoreSelector = '[data-ignore-card]';

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
            if (event.target.closest('form')) {
                return;
            }
            if (event.target.closest(ignoreSelector)) {
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

    window.GeoPhotothek = window.GeoPhotothek || {};
    window.GeoPhotothek.notifyPanelChange = (panel) => {
        if (panel !== 'dashboard') {
            return;
        }
        if (!map) {
            return;
        }
        setTimeout(() => {
            map.invalidateSize();
            if (hasVisibleMarkers && bounds) {
                map.fitBounds(bounds);
            } else if (initialBounds) {
                map.fitBounds(initialBounds);
            } else {
                map.setView(defaultView.center, defaultView.zoom);
            }
        }, 150);
    };
});

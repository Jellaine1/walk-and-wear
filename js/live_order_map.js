(function () {
    const markerStyles = {
        shop: { label: 'S', color: '#28734b', name: 'Shop' },
        customer: { label: 'C', color: '#2768a8', name: 'Customer' }
    };

    function createMap(element) {
        const map = L.map(element, { scrollWheelZoom: false }).setView([12.8797, 121.774], 5);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(map);
        return { map, markers: [], route: null };
    }

    function validPoint(point) {
        return point && Number.isFinite(Number(point.latitude)) && Number.isFinite(Number(point.longitude)) &&
            Number(point.latitude) >= -90 && Number(point.latitude) <= 90 &&
            Number(point.longitude) >= -180 && Number(point.longitude) <= 180;
    }

    function setMarker(state, role, location) {
        if (!validPoint(location)) return null;
        const style = markerStyles[role];
        const point = [Number(location.latitude), Number(location.longitude)];
        const icon = L.divIcon({
            className: 'delivery-map-marker',
            html: `<span style="--marker-color:${style.color}">${style.label}</span>`,
            iconSize: [36, 36],
            iconAnchor: [18, 18]
        });
        const marker = L.marker(point, { icon }).addTo(state.map);
        const popup = document.createElement('div');
        const title = document.createElement('strong');
        title.textContent = location.name || style.name;
        popup.append(title);
        if (location.address) {
            const address = document.createElement('div');
            address.textContent = location.address;
            popup.append(address);
        }
        marker.bindPopup(popup);
        state.markers.push(marker);
        return point;
    }

    async function showRoute(state, shop, customer, statusElement) {
        state.markers.forEach(marker => state.map.removeLayer(marker));
        state.markers = [];
        if (state.route) state.map.removeLayer(state.route);
        state.route = null;

        const points = [];
        const shopPoint = setMarker(state, 'shop', shop);
        const customerPoint = setMarker(state, 'customer', customer);
        if (shopPoint) points.push(shopPoint);
        if (customerPoint) points.push(customerPoint);
        if (points.length) state.map.fitBounds(L.latLngBounds(points), { padding: [32, 32], maxZoom: 15 });

        if (!shopPoint) {
            statusElement.textContent = 'Shop location is not configured. Ask an admin to set the fixed shop pin.';
            return;
        }
        if (!customerPoint) {
            statusElement.textContent = 'This order has no valid delivery coordinates. The customer must select a map location at checkout.';
            return;
        }

        statusElement.textContent = 'Finding the road route...';
        const coordinates = `${shopPoint[1]},${shopPoint[0]};${customerPoint[1]},${customerPoint[0]}`;
        try {
            const response = await fetch(`https://router.project-osrm.org/route/v1/driving/${coordinates}?overview=full&geometries=geojson`, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('The routing service is unavailable.');
            const result = await response.json();
            const route = result.routes?.[0];
            if (result.code !== 'Ok' || !route?.geometry?.coordinates?.length) throw new Error('No drivable road route was found between these locations.');
            state.route = L.geoJSON(route.geometry, { style: { color: '#d94a32', weight: 5, opacity: 0.9 } }).addTo(state.map);
            state.map.fitBounds(state.route.getBounds(), { padding: [32, 32], maxZoom: 15 });
            const distance = route.distance >= 1000 ? `${(route.distance / 1000).toFixed(1)} km` : `${Math.round(route.distance)} m`;
            statusElement.textContent = `${distance} by road`;
        } catch (error) {
            statusElement.textContent = `${error.message || 'Road route unavailable'} Markers are shown without a route line.`;
        }
    }

    window.WalkWearDeliveryMap = { createMap, showRoute };
})();

import { Controller } from '@hotwired/stimulus';

/*
 * Extends the ux-map Leaflet display for image-based maps: it switches the
 * map to L.CRS.Simple, draws the background through L.imageOverlay, fits the
 * initial view to the image bounds, styles the pins and the route lines, and
 * applies the pins, symbols, and lines filters.
 *
 * The leaflet controller stays the only map creator. This controller uses
 * the extension system the bundle documents: map options are overridden
 * through the "ux:map:pre-connect" bridgeOptions (the L.CRS.Simple switch),
 * the map "extra" data carries the image URL and the space bounds, and each
 * element is customized through its "before-create" bridgeOptions, like the
 * marker example in the Leaflet bridge README. It sits on the element that
 * wraps the canvas because the filter panel shares its actions.
 */
export default class extends Controller {
    static targets = ['canvas'];

    connect() {
        this.routes = [];
        this.linesMode = 'all';

        this.onPreConnect = (event) => {
            // The map shows a flat image, not a geographic projection.
            event.detail.bridgeOptions = { crs: event.detail.L.CRS.Simple };
        };

        this.onMapConnect = (event) => {
            const { map, extra, L } = event.detail;
            const bounds = [[0, 0], [extra.space.height, extra.space.width]];

            if (extra.image) {
                L.imageOverlay(extra.image.url, bounds).addTo(map);
            }

            map.fitBounds(bounds);
        };

        this.onMarkerBeforeCreate = (event) => {
            const { definition, L } = event.detail;
            const pinIcon = definition.extra?.pinIcon;

            if (pinIcon) {
                definition.bridgeOptions = {
                    icon: L.divIcon({
                        html: pinIcon.html,
                        iconSize: pinIcon.size,
                        iconAnchor: pinIcon.anchor,
                        className: '',
                    }),
                };
            }
        };

        this.onPolylineBeforeCreate = (event) => {
            const { definition } = event.detail;
            const { color, major } = definition.extra ?? {};

            definition.bridgeOptions = {
                color,
                weight: major ? 5 : 3,
                opacity: 0.9,
            };
        };

        this.onPolylineAfterCreate = (event) => {
            const { polyline, definition } = event.detail;

            this.routes.push({ polyline, major: !!definition.extra?.major });
            this.applyLinesFilter();
        };

        this.canvasTarget.addEventListener('ux:map:pre-connect', this.onPreConnect);
        this.canvasTarget.addEventListener('ux:map:connect', this.onMapConnect);
        this.canvasTarget.addEventListener('ux:map:marker:before-create', this.onMarkerBeforeCreate);
        this.canvasTarget.addEventListener('ux:map:polyline:before-create', this.onPolylineBeforeCreate);
        this.canvasTarget.addEventListener('ux:map:polyline:after-create', this.onPolylineAfterCreate);
    }

    disconnect() {
        this.canvasTarget.removeEventListener('ux:map:pre-connect', this.onPreConnect);
        this.canvasTarget.removeEventListener('ux:map:connect', this.onMapConnect);
        this.canvasTarget.removeEventListener('ux:map:marker:before-create', this.onMarkerBeforeCreate);
        this.canvasTarget.removeEventListener('ux:map:polyline:before-create', this.onPolylineBeforeCreate);
        this.canvasTarget.removeEventListener('ux:map:polyline:after-create', this.onPolylineAfterCreate);
    }

    togglePins(event) {
        this.canvasTarget.classList.toggle('app-map-pins-off', !event.currentTarget.checked);
    }

    toggleSymbols(event) {
        this.canvasTarget.classList.toggle('app-map-symbols-off', !event.currentTarget.checked);
    }

    setLines(event) {
        this.linesMode = event.currentTarget.value;
        this.applyLinesFilter();
    }

    applyLinesFilter() {
        for (const { polyline, major } of this.routes) {
            const shown = 'all' === this.linesMode || ('major' === this.linesMode && major);
            polyline.getElement()?.style.setProperty('display', shown ? '' : 'none');
        }
    }
}

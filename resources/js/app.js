import './bootstrap';

import Alpine from 'alpinejs';

import { registerChat } from './chat/index.mjs';
import { registerMaps } from './maps/index.mjs';
import { registerPlanner } from './planner/index.mjs';

// Hanya bobot yang dipakai. Lisensi ketiga font: SIL OFL 1.1 (lihat docs/KEPUTUSAN.md).
import '@fontsource-variable/playfair-display';
import '@fontsource-variable/playfair-display/wght-italic.css';
import '@fontsource-variable/inter';
import '@fontsource/courier-prime/400.css';

registerMaps(Alpine);
registerPlanner(Alpine);
registerChat(Alpine);

window.Alpine = Alpine;
Alpine.start();

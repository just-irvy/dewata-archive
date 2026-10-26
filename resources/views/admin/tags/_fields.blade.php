<x-select name="tag_type" label="Jenis tag" :options="['theme' => 'Tema editorial', 'attribute' => 'Atribut terstruktur', 'accessibility' => 'Aksesibilitas']" :selected="$record->tag_type?->value ?? 'theme'" required />
<x-textarea name="description" label="Definisi" :value="$record->description" :rows="4" hint="Definisi tema dipakai di perencana agar maknanya konsisten." />

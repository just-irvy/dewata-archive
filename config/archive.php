<?php

return [

    /*
    | Akun super-admin awal untuk seeder. Kosongkan di production bila akun dibuat
    | manual. Password tidak pernah disimpan di repository.
    */
    'admin' => [
        'name' => env('ARCHIVE_ADMIN_NAME', 'Admin Arsip'),
        'email' => env('ARCHIVE_ADMIN_EMAIL'),
        'password' => env('ARCHIVE_ADMIN_PASSWORD'),
    ],

];

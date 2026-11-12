<?php

use Illuminate\Support\Facades\Schedule;

// Memeriksa robot yang terputus (dan mengirim notifikasi) tiap menit.
Schedule::command('aeroclean:cek-offline')->everyMinute();

<?php

namespace App\Providers;

use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Ensure Windows environment variables are passed to PHP CLI server
        ServeCommand::$passthroughVariables = array_values(array_unique(array_merge(
            ServeCommand::$passthroughVariables,
            [
                'SystemDrive',
                'WINDIR',
                'COMSPEC',
                'TEMP',
                'TMP',
                'USERPROFILE',
                'LOCALAPPDATA',
                'APPDATA',
                'HOMEDRIVE',
                'HOMEPATH',
                'ProgramFiles',
                'ProgramFiles(x86)',
                'CommonProgramFiles',
                'CommonProgramFiles(x86)',
                'PROCESSOR_ARCHITECTURE',
                'NUMBER_OF_PROCESSORS',
            ]
        )));
    }
}

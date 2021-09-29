const mix = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel application. By default, we are compiling the Sass
 | file for the application as well as bundling up all the JS files.
 |
 */

if (process.env.target === 'styles') {
    mix.sass('resources/sass/app.scss', 'public/css')
        .sass('resources/sass/admin/admin.scss', 'public/css')
        .sass('resources/sass/web/web.scss', 'public/css')
        .sass('resources/sass/adminlte_custom.scss', 'public/css');

        mix.copy('resources/assets/img/', 'public/img/');
        mix.copy('resources/assets/js/custom/postal_code.js', 'public/js/');
}

if (process.env.target === 'js') {
    mix.js([
        'resources/assets/js/libs/jquery.js',
        'resources/assets/js/libs/bootstrap.js',
    ], 'public/js/libs.js');

    mix.js([
        // 'resources/assets/js/libs/jquery.js',
        'resources/assets/js/libs/bootstrap.js',
    ], 'public/js/student-libs.js');
}


if (process.env.target === 'js-app') {
    mix.js('resources/js/app.js', 'public/js')
        .webpackConfig({
            output: {
                chunkFilename: 'js/chunks/app/[name].js'
            }
        });
}

if (process.env.target === 'js-admin') {
    mix.js('resources/js/admin.js', 'public/js')
        .webpackConfig({
            output: {
                chunkFilename: 'js/chunks/admin/[name].js'
            }
        });
}


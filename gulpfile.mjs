// Include plugins
import gulp from 'gulp';
import autoprefixer from 'gulp-autoprefixer';
import minify from 'gulp-minify';

import sourcemaps from 'gulp-sourcemaps';
import cleancss from 'gulp-cleancss';
import clean from 'gulp-clean';
import eslint from 'gulp-eslint';
import zip from 'gulp-zip';
import header from 'gulp-header';

import pkg from './package.json' assert { type: 'json' };
const zipDestPath = './dist/'+ pkg.name;
const zipPath = pkg.name +'.zip';

// Define tasks
gulp.task('scripts', function() {
    return gulp.src('js/**/*.js')
    //.pipe(uglify())
        .pipe(minify({
            noSource: true,
            ext:{
                src:'-debug.js',
                min:'.js'
            },
            exclude: ['tasks'],
            ignoreFiles: ['-min.js']
        }))
        .pipe(gulp.dest('build/js'));
});

gulp.task('styles', function() {
    return gulp.src('./*.css','!./build/**/*','!./node_modules/**/*')
        .pipe(sourcemaps.init())
        .pipe(autoprefixer())
        .pipe(sourcemaps.write())
        .pipe(cleancss({keepBreaks: false}))
        .pipe(gulp.dest('build/'));
});

gulp.task('header', function() {
    const banner = ['/**',
        ' * <%= pkg.name %> - <%= pkg.description %>',
        ' * @version v<%= pkg.version %>',
        ' * @author <%= pkg.author %>',
        ' * @license <%= pkg.license %>',
        ' */',
        ''].join('\n');

    gulp.src(['./build/js/**/*.js','./build/*.css'],
        {base: './build/'})
        .pipe(header(banner, { pkg : pkg } ))
        .pipe(gulp.dest('./build/'))

    return gulp.src(['./build/**/*.php'],
        {base: './build/'})
        .pipe(header('<?php\n' + banner + '?>\n', { pkg : pkg } ))
        .pipe(gulp.dest('./build/'))
});

gulp.task('copy-to-build', function() {
    return gulp.src(['./backup/**/*','./classes/**/*','./db/**/*','./lang/**/*','./**/*.php','./README.md','./LICENSE','!./build/**/*'],
        {base: './'})
        .pipe(gulp.dest('./build/'))
});

//clean anything already in dist folder
gulp.task('clean-dist', function() {
    return gulp.src('./dist/**', {read: false, allowEmpty: true})
        .pipe(clean({force: true}));
});
//copy all files from build into folder
gulp.task('copy-dist', function() {
    return gulp.src(['./build/**/*'],
        {base: './build'})
        .pipe(gulp.dest(zipDestPath));
});
//zip folder
gulp.task('zip-dist', function() {
    return gulp.src('./dist/**')
        .pipe(zip(zipPath))
        .pipe(gulp.dest('dist'));
});
//clean copied files
gulp.task('clean-dist2', function() {
    return gulp.src(zipDestPath,'!'+zipPath,{read: false})
        .pipe(clean({force: true}));
});

gulp.task('zip', gulp.series('clean-dist','copy-dist','zip-dist','clean-dist2'));

gulp.task('clean', function () {
    return gulp.src('./build/*',{read: false,allowEmpty: true})
        .pipe(clean({force: true}));
});



gulp.task('build', gulp.series('clean', gulp.parallel('scripts', 'styles'),'copy-to-build','header'));

gulp.task('default', gulp.series('clean', gulp.parallel('scripts', 'styles')));

gulp.task('lint', () => {
    // ESLint ignores files with "node_modules" paths.
    // So, it's best to have gulp ignore the directory as well.
    // Also, Be sure to return the stream from the task;
    // Otherwise, the task may end before the stream has finished.
    return gulp.src(['js/**/*.js'])
    // eslint() attaches the lint output to the "eslint" property
    // of the file object so it can be used by other modules.
        .pipe(eslint())
        // eslint.format() outputs the lint results to the console.
        // Alternatively use eslint.formatEach() (see Docs).
        .pipe(eslint.format())
        // To have the process exit with an error code (1) on
        // lint error, return the stream and pipe to failAfterError last.
        .pipe(eslint.failAfterError());
});


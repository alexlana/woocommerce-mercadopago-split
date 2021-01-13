const gulp = require('gulp');
const git = require('gulp-git');
const uglify = require('gulp-uglify');
const rename = require('gulp-rename');
const jshint = require('gulp-jshint');
const jshintStylish = require('jshint-stylish');
const wpPot = require('gulp-wp-pot');
const cleanCSS = require('gulp-clean-css');

const config = {
  scripts: [
    './assets/js/basic_config_mercadopago.js',
    './assets/js/basic-cho.js',
    './assets/js/credit-card.js',
    './assets/js/custom_config_mercadopago.js',
    './assets/js/ticket_config_mercadopago.js',
    './assets/js/ticket.js',
    './assets/js/review.js',
  ],
  stylesheets: [
    './assets/css/admin_notice_mercadopago.css',
    './assets/css/basic_checkout_mercadopago.css',
    './assets/css/config_mercadopago.css',
  ]
};

gulp.task('jshint', function() {
  return gulp.src(config.scripts)
    .pipe(jshint('.jshintrc'))
    .pipe(jshint.reporter(jshintStylish))
    .pipe(jshint.reporter('fail'));
});

gulp.task('scripts', function() {
  return gulp.src(config.scripts)
    .pipe(uglify())
    .pipe(rename({ extname: '.min.js' }))
    .pipe(gulp.dest('./assets/js/'));
});

gulp.task('stylesheets', () => {
  return gulp.src(config.stylesheets)
    .pipe(cleanCSS({ compatibility: 'ie8' }))
    .pipe(rename({ extname: '.min.css' }))
    .pipe(gulp.dest('./assets/css/'));
});

gulp.task('wpPot', function () {
  return gulp.src('**/*.php')
        .pipe(wpPot( {
            domain: 'woocommerce-mercadopago',
            lastTranslator: 'MPB Desenvolvimento <mpb_desenvolvimento@mercadopago.com.br>',
        } ))
        .pipe(gulp.dest('./i18n/languages/woocommerce-mercadopago.pot'));
});

gulp.task('git-add', function() {
  return gulp.src('.')
    .pipe(git.add());
});

gulp.task('pre-commit', gulp.series('jshint', 'scripts', 'stylesheets', 'wpPot', 'git-add'));

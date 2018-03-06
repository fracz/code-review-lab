gulp = require('gulp')
$ = require('gulp-load-plugins')();
bowerFiles = require("main-bower-files")
argv = require('yargs').argv
del = require('del')
runSequence = require('run-sequence')

gulp.task 'bowerdeps', ->
  gulp.src(bowerFiles(), base: 'bower_components')
  .pipe(gulp.dest('results/libs'))

gulp.task 'styles', ->
  gulp.src('results_src/src/styles.css')
  .pipe(gulp.dest('results'))

gulp.task 'views', ->
  indexFilter = $.filter(['index.html', 'analysis.html']);
  gulp.src('results_src/views/**/*')
  .pipe($.changed('public'))
  .pipe(indexFilter)
  .pipe $.inject gulp.src(bowerFiles(), read: false),
    name: 'bower'
    addRootSlash: no
    ignorePath: '/bower_components/'
    addPrefix: '/results/libs'
  .pipe(indexFilter.restore())
  .pipe(gulp.dest('results'))

gulp.task 'scripts', ->
  coffeeStream = $.coffee(bare: yes)
  if not argv.production
    coffeeStream.on 'error', (error) ->
      $.util.log(error)
      coffeeStream.end()
  gulp.src('results_src/src/**/*.coffee')
  .pipe(coffeeStream)
  .pipe($.angularFilesort())
  .pipe($.ngAnnotate())
  .pipe($.concat('app.js'))
  .pipe($.if(argv.production, $.uglify()))
  .pipe(gulp.dest('results'))

gulp.task 'clean', (done) ->
  del [
    'results/**'
    '!results/.gitignore'
  ]
  ,
    done

gulp.task 'default', (done) ->
  runSequence 'clean', 'bowerdeps', 'scripts', 'views', 'styles', done

gulp.task 'watch', (done) ->
  runSequence 'clean', 'default', ->
    gulp.watch('results_src/src/**/*.coffee', ['scripts'])
    gulp.watch('results_src/views/**/*.html', ['views'])
    gulp.watch('results_src/src/styles.css', ['styles'])
    done()

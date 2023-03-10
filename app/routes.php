<?php
use Slim\Http\Request;
use Slim\Http\Response;
use Illuminate\Routing\Controller;
use Ncx\Controllers\NcxController;
use Ncx\Controllers\UploadController;
use Ncx\Controllers\PhotoStreamController;
use Ncx\Controllers\VideoStreamController;

//dd($app);

# Routes
$app->get('/', NcxController::class.':index')->setName('ncx.index');
//Upload Controller
$app->options('/upload/{identifier}', function($req,$res,$args){return $res;})->setName('file.upload.options');
$app->post('/upload/{identifier}', UploadController::class . ':upload')->add($jwtAuthorized)->setName('file.upload');
$app->delete('/delete/{mediatype}/{id}/{identifier}', UploadController::class . ':delete')->add($jwtAuthorized)->setName('file.delete');

$app->get('/photo/full/{id}.{ext}', PhotoStreamController::class.':full')->setName('photo.full'); //reserved, non-modified image
#$app->get('/photo/thumb/{id}.{ext}', PhotoStreamController::class.':thumb')->setName('photo.thumb');
$app->get('/photo/{type}/{id}.{ext}', PhotoStreamController::class.':thumb')->setName('photo.thumb');


$app->get('/api/video/{id}.{ext}/info', VideoStreamController::class.':info')->setName('video.info');
$app->get('/video/{id}.{ext}', VideoStreamController::class.':stream')->setName('video.stream');
$app->get('/video/{id}/thumb.jpg', VideoStreamController::class.':thumb')->setName('video.thumb');

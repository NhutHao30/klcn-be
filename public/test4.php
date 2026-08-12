<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$token = auth('api')->attempt(['USERNAME' => 'admin', 'PASSWORD' => '123']);
$request = Illuminate\Http\Request::create('/api/chat/internal/managers/messages', 'POST', ['NOIDUNG' => 'test message']);
$request->headers->set('Authorization', 'Bearer ' . $token);
$request->headers->set('Accept', 'application/json');

$response = $kernel->handle($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo "Content: " . $response->getContent() . "\n";

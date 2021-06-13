<?php

require_once('Response.php');

$response = new Response();

$response->setSuccess(true);
$response->setHttpStatusCode(200);
$response->setMessages("Welcome");
$response->setMessages("Every thing is good");
$response->send();

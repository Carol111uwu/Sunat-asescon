<?php
declare(strict_types=1);
interface ContratoHttpSunat
{
 public function postForm(string $url,array $form,array $headers=[]):array;
 public function getJson(string $url,array $headers=[]):array;
 public function postJson(string $url,?array $payload=null,array $headers=[]):array;
 public function getBinary(string $url,array $headers=[]):array;
}

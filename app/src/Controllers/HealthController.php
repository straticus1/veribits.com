<?php
namespace VeriBits\Controllers;
use VeriBits\Utils\Response;

class HealthController {
    public function status(): void {
        Response::json(['status'=>'ok','service'=>'veribits','time'=>gmdate('c')]);
    }
}

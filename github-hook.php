<?php
define('SECRET_KEY', ''); //Secret key set on GitHub webhook

if(!empty($_POST['payload'])) {
    $headers = getallheaders();
    $payload = file_get_contents('php://input');

    $signatureKey = $headers['X-Hub-Signature'];

    $key = explode('=', $signatureKey);

    $algorithm = $key[0];
    $hash = $key[1];

    $hashPayload = hash_hmac($algorithm, $payload, SECRET_KEY);

    $payload = json_decode($_POST['payload']);

    if($hash === $hashPayload) {
        if(!empty($payload->ref)) {
            if($payload->ref == "refs/heads/master") {
                echo 'Push on master !';
                shell_exec("./deploy.sh --release 2>&1");
            } else {
                echo 'Error : Push is not on master, abort.';
            }
        } else {
            echo 'Error : ref not set.';
        }
    } else {
        echo 'Error : secret key doesn\'t match';
    }
} else {
    echo 'Error : payload not set.';
}
?>

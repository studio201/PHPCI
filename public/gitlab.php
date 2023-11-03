<?php

// Capture all GET and POST variables
$allVariables = array_merge($_GET, $_POST);
$PROJECT_ID = $_GET["PROJECT_ID"];

if ($payload = file_get_contents('php://input')) {
    try {
        $payload = json_decode($payload, true);
    } catch (Exception $ex) {
        echo $ex;
        exit(0);
    }
    // put the branch you want here
    //  if($payload->ref != "refs/heads/master") {
    //          echo "wrong head";
    //          exit(0);
    //  }
    //put the branch you want here, as well as the directory your site is in
    //  $result = `cd /var/www/website && git fetch origin && git merge origin/master`;

    //  echo $result;
} else {
    die("failed request");
}


// Capture all HTTP headers
$headers = getallheaders();

// Combine variables and headers into a single array
$data = array(
    'Variables' => $allVariables,
    'Headers' => $headers,
    'Payload' => $payload
);


function trigger_hook($PROJECT_ID, $NEWREV, $REFNAME, $COMMITTER, $MESSAGE)
{
    $PHPCI_URL = "https://test.314.de:8080";
    if ($NEWREV === "0000000000000000000000000000000000000000") {
        // Ignore deletion
        return;
    }

    // Check if it's a branch or tag reference
    if (preg_match('/^refs\/(heads|tags)\/(.+)$/', $REFNAME, $matches)) {
        $BRANCH = $matches[2];
        // $COMMITTER = shell_exec("git log -1 $NEWREV --pretty=format:%ce");
        // $MESSAGE = shell_exec("git log -1 $NEWREV --pretty=format:%s");

        $data = [
            'branch' => $BRANCH,
            'commit' => $NEWREV,
            'committer' => $COMMITTER,
            'message' => $MESSAGE
        ];

        $postData = http_build_query($data);
        $url = "$PHPCI_URL/webhook/git/$PROJECT_ID";
        $ch = curl_init($url);
        echo "Sending webhook to $url";

        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        print_r($response);
        // Optionally, you can print the response if needed
        // echo "Response: $response\n";
    }
}

if (sizeof($payload) > 0) {

    $newref = $payload["after"];
    $refname = $payload["ref"];
    $comitter = $payload["user_username"];
    $message = $payload["commits"][0]["message"];
    trigger_hook($PROJECT_ID, $newref, $refname, $comitter, $message);
} else {
    echo "no payload";
}
?>

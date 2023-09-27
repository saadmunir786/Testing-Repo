<?php
if (isset($_POST['MD'], $_POST['PaReq'], $_POST['TermUrl'])) {
    $md = $_POST['MD'];
    $paReg = $_POST['PaReq'];
    $termUrl = $_POST['TermUrl'];

    $decodedPaReg = json_decode(base64_decode($paReg), true);
    $decodedMd = explode('|', base64_decode($md));

    if ($decodedPaReg !== null && is_array($decodedMd) && $decodedMd[0] === 'CREDITCARD-3DS2') {
        $successUrl = strpos($termUrl, '?') !== false ? $termUrl . '&state=ok' : $termUrl . '?state=ok';
        $failureUrl = strpos($termUrl, '?') !== false ? $termUrl . '&state=nok' : $termUrl . '?state=nok';
    } else {
        echo 'Invalid payload';
        exit;
    }
} else {
    echo 'Missing payload';
    exit;
}
?>
<html>
<head>
    <meta charset="UTF-8">
    <title>ACS Proxy</title>
    <script>
        function getCookie(name) {
          const value = `; ${document.cookie}`;
          const parts = value.split(`; ${name}=`);
          if (parts.length === 2) return parts.pop().split(';').shift();
        }

          /**
         * Sends window messages received to logger
         * */
        function logMessagePost(page='ACS', type='raw', event) {
          var logData = {
            command: 'log',
            page: page,
            type: type,
            payload: type=='raw' ? JSON.parse(atob(event.data)) : event
          };

          console.log(logData);

          var logUrl = getCookie('SIDPath') + '/jsonapi.php?' + getCookie('SIDString');
          var xhr = new XMLHttpRequest();
          xhr.open('POST', logUrl, false);
          xhr.setRequestHeader('Accept', 'application/json');
          xhr.setRequestHeader('Content-Type', 'application/json;charset=UTF-8');

          xhr.onreadystatechange = function () {
            if (xhr.readyState === XMLHttpRequest.DONE) {
              var status = xhr.status;
              if (status == 200) {
                try {
                  var response = JSON.parse(this.responseText);
                  // console.log('jsonapi: ' + response);
                }
                catch (e) {
                  // we ignore log response
                }
              }
            }
          };
          var requestBody = JSON.stringify(logData);
          xhr.send(requestBody);
        }

        window.addEventListener("message", postMessageHandler, false);

        function postMessageHandler(event) {
            try {
                var decodedData = JSON.parse(atob(event.data));
                handleAuthenticationResponse(decodedData, event.data);
                handleChallengeResponse(decodedData, event.data);
            } catch(e) {
                console.log('Invalid post message event data received');
                console.log(event);
                console.log(e);
            }
        }

        function handleAuthenticationResponse(ares, rawData) {
            if (ares.hasOwnProperty('acsChallengeMandated') && ares.hasOwnProperty('acsURL') && ares.hasOwnProperty('base64EncodedChallengeRequest') && ares.hasOwnProperty('challengeWindowSize')) {
                console.log('Authentication response received');
                logMessagePost('ACS', 'text', 'Authentication response received');
                logMessagePost('ACS', 'raw', event);

                if (ares.hasOwnProperty('acsChallengeMandated') && ares.acsChallengeMandated === true) {
                    console.log('Challenge requested');
                    logMessagePost('ACS', 'text', 'Challenge requested');

                    document.getElementById("authentication").style.display = 'none';
                    document.getElementById("progress").style.display = 'none';
                    document.getElementById("return").style.display = 'none';
                    document.getElementById("challengeIframe").style.width = '100%';
                    document.getElementById("challengeIframe").style.height = '100%';

                    document.getElementById('challengeForm').action = ares.acsURL;
                    document.getElementById('creq').value = ares.base64EncodedChallengeRequest;
                    document.challengeForm.submit();
                }
            }
        }

        function handleChallengeResponse(cres, rawData) {
            if (cres.hasOwnProperty('MID') && cres.hasOwnProperty('Len') && cres.hasOwnProperty('Data')) {
                console.log('Challenge response received');
                logMessagePost('ACS', 'text', 'Challenge response received');
                logMessagePost('ACS', 'raw', event);

                document.getElementById("challenge").style.display = 'none';
                document.getElementById("return").style.display = 'block';
                document.getElementById("progress").style.display = 'block';

                document.getElementById('PaRes').value = rawData;
                document.returnForm.submit();
            }
        }
    </script>
</head>
<body>
    <div id="progress">
        <img src="./acs/img/loading.gif" width="40" alt="Processing..." />
    </div>
    <div id="authentication">
        <form name="authenticationForm" id="authenticationForm" method="post" action="<?= $decodedPaReg['threeDSMethodURL'] ?>" target="authenticationIframe">
            <input type="hidden" name="threeDSMethodData" value="<?= $decodedPaReg['threeDSMethodDataForm'] ?>" />
        </form>
        <iframe name="authenticationIframe" id="authenticationIframe" frameBorder="0"></iframe>
    </div>
    <div id="challenge">
        <form name="challengeForm" id="challengeForm" method="post" action="" target="challengeIframe">
            <input type="hidden" name="creq" id="creq" value="" />
        </form>
        <iframe name="challengeIframe" id="challengeIframe" frameBorder="0"></iframe>
    </div>
    <div id="return">
        <form name="returnForm" id="returnForm" method="post" action="<?= $successUrl ?>">
            <input type="hidden" name="PaRes" id="PaRes" value="" />
            <input type="hidden" name="MD" id="MD" value="<?= $md ?>" />
        </form>
    </div>
    <script>
        document.authenticationForm.submit();
    </script>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Maintenance</title>
    <link rel="stylesheet" href="//netdna.bootstrapcdn.com/bootstrap/3.1.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="//netdna.bootstrapcdn.com/bootstrap/3.1.0/css/bootstrap-theme.min.css">
</head>
<body style="padding-top:40px; background-color:#F7F7F9">
<div class="container">
    <div class="row">
        <div class="col-md-offset-4 col-md-4">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">Offline Install</h3>
                </div>
                <div class="panel-body text-center">
                    <p>claroffline est en cours d'installation</p>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
<?php
    ini_set('max_execution_time', 0);
    echo("Je me lance<br/>");
    $ds = DIRECTORY_SEPARATOR;
    $command = 'php '.__DIR__.$ds.'..'.$ds.'app'.$ds.'console claroline:install';
    exec($command);
    var_dump($command);
    echo "termine<br/>";
    // TODO modif setInstall true
    // redirect app_offline
    ?>
    
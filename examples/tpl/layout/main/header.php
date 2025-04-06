<!DOCTYPE html>
<html lang="ru">
<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta http-equiv="Content-Type" content="text/html;charset=UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="author" content="Akimov MA">
    <meta name="generator" content="MiniLib PHP" />
    <meta name='yandex-verification' content='' />

    <!-- link href="/favicon.ico" rel="icon" type="image/x-icon" /-->
    <title><?php echo $this->title>''? $this->title.' // ': ''?> make with MiniLib</title>
    
    <?php if($this->getProperty('og-title') > ''): ?>
    <meta property="og:title" content="<?php echo $this->getProperty('og-title') ?>">
    <?php else: ?>
    <meta property="og:title" content="<?php echo $this->getTitle() ?>">
    <?php endif; ?>
    <meta property="og:description" content="<?php echo $this->getProperty('og-description')?>" />
    <meta property="og:type" content="website" />
    <meta property="og:image" content="<?php echo $this->getProperty('og-image')?>" />


    <!-- Latest compiled and minified CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
    
    <!-- Optional theme -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap-theme.min.css" integrity="sha384-rHyoN1iRsVXV4nD0JutlnGaslCJuC7uwjduW9SVrLvRYooPp2bWYgmgJQIXwl/Sp" crossorigin="anonymous">

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
      <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->

</head>
<body>
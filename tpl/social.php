<!DOCTYPE html>
<html>
    <head>
        <title><?php echo section('title'); ?> &mdash; GNU social</title>


   <link rel="stylesheet" href="/theme/gnusocial/css/combo.css" type="text/css">
   <link rel="stylesheet" href="/theme/gnusocial/css/social.css" type="text/css">
        <?php echo section('scripts'); ?>
        <?php echo section('search'); ?>
        <?php echo section('feeds'); ?>
        <?php echo section('description'); ?>
        <?php echo section('head'); ?>
        </head>
    <body id="<?php echo section('action'); ?>">
        <div id="custom-doc" class="yui-t2">
           <div id="hd">
                <h1><a href="/">GNU social</a></h1>            
                <?php echo section('nav'); ?>
            </div>
            <div id="bd">
                <div id="yui-main">
                    <div class="yui-b" id="social">
                        <div class="yui-gc">
                            <div class="yui-u first">
                                <?php echo section('noticeform'); ?>
                                <?php echo section('bodytext'); ?>

                            </div>


                            <div class="yui-u" id="right-nav">
                                <div id="aside_primary" class="aside">
                                    <?php echo section('subscriptions'); ?>
                                    <?php echo section('subscribers'); ?>
                                    <?php echo section('groups'); ?>
                                    <?php echo section('cloud'); ?>
                                    <?php echo section('popular'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="yui-b" id="sidebar">
                    <?php echo section('localnav'); ?>
                </div>
            </div>
            <div id="ft">
                <p>This is GNU social.</p>
            </div>
        </div>
    </body>
</html>

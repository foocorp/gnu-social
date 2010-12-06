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

       <div id="feedback-button-of-doom"><a href="http://social.shapado.com/"><img src="/themes/gnusocial/images/fback.png" title="Send us your ideas and suggestions" alt="Feedback" /></a></div>


        <div id="doc2" class="yui-t6">
           <div id="hd">
                <h1><a href="/">GNU social</a></h1>            
                <?php echo section('nav'); ?>
            </div>
            <div id="bd">
                <div id="yui-main">
                    <div class="yui-b" id="social">
                        <div class="yui-g">
                                <?php echo section('noticeform'); ?>
                                <?php echo section('bodytext'); ?>
                            </div>
</div>
</div>


                            <div class="yui-b" id="right-nav">
                                <div id="aside_primary" class="aside">
                                    <?php echo section('subscriptions'); ?>
                                    <?php echo section('subscribers'); ?>
                                    <?php echo section('groups'); ?>
                                    <?php echo section('cloud'); ?>
                                    <?php echo section('popular'); ?>
                                    <?php echo section('localnav'); ?>
                                </div>
                            </div>
            <div id="ft">
	      <p>This is <a href="http://www.gnu.org/software/social">GNU social</a> &mdash; licensed under the <a href="http://www.gnu.org/licenses/agpl-3.0.html">GNU Affero General Public License</a> version 3.0 or later. <a href="http://gitorious.org/+socialites/statusnet/gnu-social">Get the code</a>.</p>
            </div>
        </div>
    </body>
</html>

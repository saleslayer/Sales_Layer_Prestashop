<head>{strip}
    <!--<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">-->
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.2.0/css/all.css" integrity="sha384-hWVjflwFxL6sNzntih27bfxkr27PmbbK/iSvJ+a4+0owXq79v+lsFkW54bOGbiDQ" crossorigin="anonymous">
    <link rel="stylesheet" href="{$SLY_ASSETS_PATH}views/css/slyrimport.css"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <style>
    </style>
</head>
<body>
<div class="container mar-top-btt-40">
    <div class="row">
        <div class="col-lg-4 col-md-4 col-sm-12 pad-10">
            <img src="{$SLY_LOGOS_PATH}logob_{$COMPANY_TYPE}.png" height="65px;" alt="logo sales layer" class="max-h-50p" >
        </div>
        <div class="col-lg-8 col-md-8 col-xs-12 pad-10" id="slh1selector">
            <h1>Prestashop plugin for import catalogues and products.</h1>
        </div>

    </div>
    <div class="row">
        <div class="col-xs-12 pad-10 mar-top-btt-40 text-center">
            <span class="pull-left sy-done" id="sllisted"></span><span class="pull-left" id="slwarnings"></span><span class="sy-error pad-10" id="slerrors">{$message}</span>
        </div>
    </div>
    <div class="row mar-top-btt-40">
        <div class="col-md-3 mar-top-btt-40">
            <a href="{$link_all_connectors}" class="btn btn-success width-150 mar-top-btt-10" ><i class="fa fa-eye" aria-hidden="true"></i> View Connectors</a>
        </div>
        <div class="col-md-3  mar-top-btt-40">
            <a href="{$add_connectors}" class="btn btn-success width-150 mar-top-btt-10"><i class="fa fa-plus text-left" aria-hidden="true"></i> Add Connector</a>
        </div>
        <div class="col-md-3  mar-top-btt-40">
            <a href="{$link_how_to_use}" class="btn btn-success width-150 mar-top-btt-10"><i class="fa fa-info text-left" aria-hidden="true"></i> How To Use</a>
        </div>
        <div class="col-md-3  mar-top-btt-40">
            <a href="{$link_diagnostics}" class="btn btn-success width-150 mar-top-btt-10"><i class="fa fa-fire-extinguisher text-left" aria-hidden="true"></i> Diagnostics</a>
        </div>
    </div>
    <div>
        <section class="row mar-top-btt-10">
            <h3><strong>{$plugin_name}</strong> plugin allows you to add in your Prestashop website all your catalogue super easily. To do so, the catalog automatically imports and syncs all the product information.</h3>
            <p>First of all the plugin needs the <strong>connector ID code</strong> and the <strong>private key</strong>. You will find them in the connector details of <strong>Sales Layer PIM</strong>.</p>
        </section>
        <section class="row mar-top-btt-10">
            <ol class="mar-top-btt-10">
                <li>Go to <a href="{$add_connectors}">Sales Layer -> Add New Connector</a></li>
                <li>Add the connection credentials.</li>
                <li>In tab <a href="{$link_all_connectors}">connectors</a>, change the value of auto sync to the desired time frequency to sync each connector.</li>
                <li>The store must be assigned a root category to perform syncronization correctly.</li>
            </ol>
        </section>
        <section class="row mar-top-btt-10">
            <h3>Requirements for synchronization</h3>
        </section>
        <section class="row mar-top-btt-10">
            <ol>
                <li><strong>cURL</strong> extension installed; In order to call and obtain the information from Sales Layer</strong>.</li>
                <li>Define the fields relationship in the Sales Layer PIM</strong> Prestashop connector: </li>
                <ol>
                    <li>One size for image fields.</li>
                    <li>Most of the Prestashop fields are already defined in each product section and variants in the configuration of the Sales Layer cloud connector. Additional fields for products will become features and additional fields of variants will become attributes of variants.</li>
                    <li>When synchronizing a product that has formats, Prestashop attributes that are synchronized will be marked as <strong>Used for variations</strong>, then, attribute values from the product and product formats will be combined and assigned to the product. Variations must have only one value for each attribute.</li>
                </ol>
            </ol>
            <div class="row text-center mar-top-btt-40">
                <div class="col-md-12">
                    {$validation_table}
                </div>
            </div>
        </section>
        <section class="row mar-top-btt-10">
            <h3>How To Synchronize By Cron</h3>
        </section>
        <section class="row">
            <div class="col-md-12">
                <h4>To enable automatic synchronization, have an active cron job on your server and verify if the prestashop cron is running. If not, you can do it in the following way:</h4>
                <h4>If result "<b>Registered prestashop cronjob activity</b>" is equal to <i class="fa fa-check text-success" aria-hidden="true"></i> the cron jobs of Prestashop they are executed correctly, means that in the last hour cron job of sales layer has been executed at least once. The subsequent configuration is not necessary.</h4>
                <div class="col-md-12">
                    <ul class="list-unstyled">
                        <li class="mar-top-btt-10"><b>If cpanel is used:</b></li>
                        <li>Loged on cpanel click the Cron Jobs icon.</li>
                        <li>Add New Cron Job and create new cron job with the following values:</li><br>
                        <li>
                            <ul class="list-unstyled mar-top-btt-10">
                                <li>Minute:  */5</li>
                                <li>Hour:    *</li>
                                <li>Day:     *</li>
                                <li>Month:   *</li>
                                <li>Weekday: *</li>
                                <li>Command:  </li>
                                <li class="mar-top-btt-10">
                                    <ol>
                                        <li><strong>wget -O /dev/null {$culr_link} > /dev/null 2>&1</strong></li>
                                        <p>or</p>
                                        <li><strong>curl "{$culr_link}" > /dev/null 2>&1 </strong></li>
                                    </ol>
                                </li>
                            </ul>
                        </li>
                   </ul>
                </div>
                <div class="col-md-12">
                   <ul class="list-unstyled">
                        <li class="mar-top-btt-10"><b>By ssh console:</b><br></li>
                        <li>Execute command on your console: <b>'crontab -e'</b></li>
                        <li>If there are not any of the following lines that contain the same url add a line at the end of that list.</li>
                        <li class="mar-top-btt-10">
                            <ol>
                                <li><strong>*/5 * * * *  wget -O /dev/null {$culr_link}</strong></li>
                                <p>or</p>
                                <li><strong>*/5 * * * *  curl "{$culr_link}" </strong></li>
                            </ol>
                        </li>
                   </ul>
                </div>
            </div>
        </section>
    </div>
</div>{/strip}{literal}
    <script>
    </script>{/literal}
</body>
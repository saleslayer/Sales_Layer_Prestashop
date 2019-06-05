<head>{strip}
        <!--<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">-->
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.2.0/css/all.css" integrity="sha384-hWVjflwFxL6sNzntih27bfxkr27PmbbK/iSvJ+a4+0owXq79v+lsFkW54bOGbiDQ" crossorigin="anonymous">
        <link rel="stylesheet" href="{$SLY_ASSETS_PATH}views/css/slyrimport.css"/>
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <style>
            .mar-auto{ margin: auto; width: 50% !important;}
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
        <div class="row">
            <div class="col-lg-5 col-md-4 col-xs-12 pad-10 forinfo">
                <span class="pull-left" id="sllisted"></span><span class="pull-left" id="slwarnings"></span><span class="pull-left" id="slerrors"></span>
            </div>
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
        <div class="mar-top-btt-40">
            <h2>Other configurations</h2>
        </div>
        <div class="mar-top-btt-40 form-group">
            <label>Maximum limit of items per process</label>
            <input type="text" name="ithems_limit" value="{$limit_ithems_for_process}">
        </div>
    </div>
</div>{/strip}{literal}
    <script>
    </script>{/literal}
</body>
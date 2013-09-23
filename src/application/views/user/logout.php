<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="content-type" content="text/html; charset=UTF-8">
    
    <?php // INCLUDE JS FILES ?>
    <script 
        type="text/javascript" 
        src="<?php echo $this->config->base_url(); ?>assets/js/libs/jquery/jquery.min.js"></script>
    <script 
        type="text/javascript" 
        src="<?php echo $this->config->base_url(); ?>assets/js/libs/bootstrap/bootstrap.min.js"></script>   
        
    <?php // INCLUDE CSS FILES ?>
    <link 
        rel="stylesheet" 
        type="text/css" 
        href="<?php echo $this->config->base_url(); ?>assets/css/libs/bootstrap/bootstrap.css">
    <link 
        rel="stylesheet" 
        type="text/css" 
        href="<?php echo $this->config->base_url(); ?>assets/css/libs/bootstrap/bootstrap-responsive.css">
    
    <?php // SET FAVICON FOR PAGE ?>
    <link 
        rel="icon" 
        type="image/x-icon" 
        href="<?php echo $this->config->base_url(); ?>assets/images/favicon.ico">
    
    <style>
        body {
            background-color: #CAEDF3;
        }
        
        #logout-frame {
            width: 630px;
            margin: 150px auto 0 auto;
            background: #FFF;
            border: 1px solid #DDDADA;
            padding: 70px;
        }
        
        #login-icon {
            float: right;
            margin-top: 17px;
        }
        
        label {
            font-weight: bold;
        }
        
        .btn {
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div id="logout-frame" class="frame-container">
        <h3>Logout</h3>
        <p>
            You have been successfully logged out! Click on one of the following buttons to 
            navigate to a different page.
        </p>  
        
        <br>
        
        <a href="<?php echo $this->config->base_url(); ?>" class="btn btn-primary btn-large">
            <i class="icon-calendar icon-white"></i>
            Book Appointment
        </a>
        
        <a href="<?php echo $this->config->base_url(); ?>backend" class="btn btn-danger btn-large">
            <i class="icon-wrench icon-white"></i>
            Backend Section
        </a>
    </div>
</body>
</html>
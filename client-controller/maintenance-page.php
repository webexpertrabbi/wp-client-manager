<?php
// Get custom data from options, with defaults
$title = get_option('wpcm_maintenance_title', 'Under Maintenance');
$logo_url = get_option('wpcm_maintenance_logo_url', '');
$text = get_option('wpcm_maintenance_text', 'Our website is currently undergoing scheduled maintenance. We should be back online shortly. Thank you for your patience.');

// Send headers
header('HTTP/1.1 503 Service Unavailable');
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($title); ?></title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #f1f1f1;
            color: #444;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            text-align: center;
        }

        .container {
            max-width: 600px;
            padding: 40px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        h1 {
            font-size: 32px;
            color: #222;
            margin-top: 0;
        }

        p {
            font-size: 18px;
            line-height: 1.6;
        }

        .icon {
            font-size: 50px;
            margin-bottom: 20px;
            color: #D94F4F;
        }
    </style>
</head>

<body>
    <div class="container">
        <?php if (!empty($logo_url)) : ?>
            <img src="<?php echo esc_url($logo_url); ?>" alt="Logo" style="max-width: 200px; margin-bottom: 20px;">
        <?php else : ?>
            <div class="icon">&#9881;</div>
        <?php endif; ?>

        <h1><?php echo esc_html($title); ?></h1>

        <?php echo wpautop($text); // Use wpautop to render HTML and paragraphs correctly 
        ?>
    </div>
</body>

</html>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>FoodHub Password Reset</title>
</head>

<body style="font-family: Arial, sans-serif; background:#f5f5f5; padding:30px;">

    <div style="max-width:600px; margin:auto; background:white; padding:30px; border-radius:10px;">

        <h2>FoodHub Password Reset</h2>

        <p>Hello,</p>

        <p>
            We received a request to reset your FoodHub account password.
        </p>

        <p>
            Your password reset verification code is:
        </p>

        <div style="font-size:32px; font-weight:bold; letter-spacing:8px; text-align:center; margin:30px 0;">
            {{ $otp }}
        </div>

        <p>
            This verification code will expire in <strong>10 minutes</strong>.
        </p>

        <p>
            If you did not request a password reset, you can safely ignore this email.
        </p>

        <p>
            Regards,<br>
            <strong>FoodHub Team</strong>
        </p>

    </div>

</body>
</html>
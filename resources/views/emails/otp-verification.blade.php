<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>FoodHub Email Verification</title>
</head>

<body style="font-family: Arial, sans-serif; background-color: #f5f5f5; padding: 30px;">

    <div style="max-width: 500px; margin: auto; background: #ffffff; padding: 30px; border-radius: 12px;">

        <h2 style="text-align: center; color: #333333;">
            FoodHub
        </h2>

        <h3>Verify your email</h3>

        <p>
            Hello {{ $user->name }},
        </p>

        <p>
            Thank you for registering with FoodHub.
            Please use the verification code below to verify your email address.
        </p>

        <div style="text-align: center; margin: 30px 0;">
            <span style="
                display: inline-block;
                background: #f0f0f0;
                padding: 15px 25px;
                border-radius: 8px;
                font-size: 32px;
                font-weight: bold;
                letter-spacing: 8px;
            ">
                {{ $otp }}
            </span>
        </div>

        <p>
            This verification code will expire in <strong>10 minutes</strong>.
        </p>

        <p>
            If you did not create a FoodHub account, you can safely ignore this email.
        </p>

        <p>
            Regards,<br>
            <strong>FoodHub Team</strong>
        </p>

    </div>

</body>
</html>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset OTP - URL Manager Pro</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f8fafc; font-family: 'Inter', Arial, sans-serif;">

    <table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #f8fafc; padding: 20px 0;">
        <tr>
            <td align="center">
                <table width="600" border="0" cellspacing="0" cellpadding="0" 
                    style="background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 8px 20px rgba(0,0,0,0.08);">
                    
                    <!-- Header -->
                    <tr>
                        <td align="center" style="background-color: #2563eb; padding: 40px 20px;">
                            <h1 style="color: #ffffff; font-size: 28px; margin: 0; font-weight: 700; letter-spacing: 0.5px;">
                                URL Manager Pro
                            </h1>
                            <p style="color: #cfe1ff; margin-top: 8px; font-size: 14px;">
                                Secure Password Recovery
                            </p>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px 35px; text-align: left;">
                            <h2 style="color: #111827; margin: 0 0 15px 0; font-size: 22px; font-weight: 600;">
                                Forgot Your Password?
                            </h2>
                            <p style="color: #4b5563; font-size: 16px; line-height: 1.7; margin: 0;">
                                We received a request to reset your password for your 
                                <strong>URL Manager Pro</strong> account. Use the One-Time Password (OTP) below to proceed:
                            </p>

                            <!-- OTP Box -->
                            <div style="text-align: center; margin: 35px 0;">
                                <span style="
                                    display: inline-block;
                                    background: linear-gradient(135deg, #2563eb, #3b82f6);
                                    color: #ffffff;
                                    font-size: 30px;
                                    font-weight: 700;
                                    padding: 16px 36px;
                                    border-radius: 10px;
                                    letter-spacing: 6px;
                                    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
                                ">
                                    {{ $otp }}
                                </span>
                            </div>

                            <p style="color: #6b7280; font-size: 14px; text-align: center; margin-bottom: 35px;">
                                This OTP will expire in <strong>10 minutes</strong>. Do not share it with anyone.
                            </p>

                            <p style="color: #4b5563; font-size: 15px; line-height: 1.7;">
                                If you didn’t request a password reset, please ignore this message — your account remains secure.
                            </p>
                        </td>
                    </tr>

                    <!-- Divider -->
                    <tr>
                        <td style="padding: 0 35px;">
                            <hr style="border: none; height: 1px; background-color: #e5e7eb; margin: 0;">
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="center" style="padding: 25px 20px; background-color: #f9fafb;">
                            <p style="color: #9ca3af; font-size: 13px; margin: 0 0 10px;">
                                &copy; {{ date('Y') }} <strong>URL Manager Pro</strong>. All rights reserved.
                            </p>
                            <p style="color: #9ca3af; font-size: 12px; margin: 0;">
                                This is an automated email. Please do not reply.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>

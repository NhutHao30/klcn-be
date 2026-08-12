<!DOCTYPE html>
<html>
<head>
    <title>Yêu cầu liên hệ mới</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
        <h2 style="color: #b88a44; border-bottom: 2px solid #b88a44; padding-bottom: 10px;">Yêu cầu liên hệ mới từ khách hàng</h2>
        
        <p>Xin chào quản trị viên Dola Bakery,</p>
        <p>Bạn vừa nhận được một yêu cầu liên hệ mới với thông tin như sau:</p>
        
        <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
            <tr>
                <td style="padding: 10px; border: 1px solid #eee; background-color: #f9f9f9; width: 30%; font-weight: bold;">Họ và tên:</td>
                <td style="padding: 10px; border: 1px solid #eee;">{{ $contactData['name'] }}</td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #eee; background-color: #f9f9f9; font-weight: bold;">Email:</td>
                <td style="padding: 10px; border: 1px solid #eee;">{{ $contactData['email'] }}</td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #eee; background-color: #f9f9f9; font-weight: bold;">Số điện thoại:</td>
                <td style="padding: 10px; border: 1px solid #eee;">{{ $contactData['phone'] }}</td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #eee; background-color: #f9f9f9; font-weight: bold;">Nội dung:</td>
                <td style="padding: 10px; border: 1px solid #eee; white-space: pre-wrap;">{{ $contactData['message'] }}</td>
            </tr>
        </table>
        
        <p style="margin-top: 20px; font-size: 0.9em; color: #777;">
            Vui lòng phản hồi khách hàng trong thời gian sớm nhất.<br>
            Email này được gửi tự động từ hệ thống Website Dola Bakery.
        </p>
    </div>
</body>
</html>

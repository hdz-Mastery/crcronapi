<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 10px 10px 0 0;
        }
        .content {
            background: #f9f9f9;
            padding: 30px;
            border-radius: 0 0 10px 10px;
        }
        .credentials {
            background: white;
            padding: 20px;
            border-radius: 5px;
            border-left: 4px solid #667eea;
            margin: 20px 0;
        }
        .credential-item {
            margin: 10px 0;
        }
        .credential-label {
            font-weight: bold;
            color: #667eea;
        }
        .credential-value {
            font-family: 'Courier New', monospace;
            background: #f0f0f0;
            padding: 5px 10px;
            border-radius: 3px;
            display: inline-block;
            margin-top: 5px;
        }
        .warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .button {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>¡Bienvenido a CRCRON!</h1>
    </div>
    
    <div class="content">
        <p>Hola <strong>{{ $user->username }}</strong>,</p>
        
        <p>Tu cuenta ha sido creada exitosamente en la plataforma CRCRON. A continuación encontrarás tus credenciales de acceso:</p>
        
        <div class="credentials">
            <div class="credential-item">
                <div class="credential-label">📧 Correo electrónico:</div>
                <div class="credential-value">{{ $user->email }}</div>
            </div>
            
            <div class="credential-item">
                <div class="credential-label">👤 Usuario:</div>
                <div class="credential-value">{{ $user->username }}</div>
            </div>
            
            <div class="credential-item">
                <div class="credential-label">🔑 Contraseña temporal:</div>
                <div class="credential-value">{{ $temporaryPassword }}</div>
            </div>
        </div>
        
        <div class="warning">
            <strong>⚠️ Importante:</strong> Por tu seguridad, te recomendamos cambiar esta contraseña temporal en tu primer inicio de sesión.
        </div>
        
        <center>
            <a href="{{ config('app.url') }}/login" class="button">
                Iniciar Sesión
            </a>
        </center>
        
        <p>Si tienes alguna pregunta o necesitas ayuda, no dudes en contactar con nuestro equipo de soporte.</p>
        
        <p>¡Gracias por confiar en CRCRON!</p>
    </div>
    
    <div class="footer">
        <p>Este es un correo automático, por favor no respondas a este mensaje.</p>
        <p>&copy; {{ date('Y') }} CRCRON. Todos los derechos reservados.</p>
    </div>
</body>
</html>
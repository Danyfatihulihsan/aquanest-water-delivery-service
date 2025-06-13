<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Halaman Tidak Ditemukan</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            color: #343a40;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            overflow: hidden;
            position: relative;
        }
        
        .container {
            max-width: 800px;
            text-align: center;
            padding: 2rem;
            position: relative;
            z-index: 10;
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 20px;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
            animation: fadeIn 1s ease-out;
        }
        
        .error-code {
            font-size: 10rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #5c7cfa;
            line-height: 0.8;
            text-shadow: 3px 3px 0 #dee2e6;
        }
        
        h1 {
            font-size: 2.5rem;
            margin-bottom: 1.5rem;
            color: #343a40;
        }
        
        p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            color: #6c757d;
            line-height: 1.6;
        }
        
        .btn {
            display: inline-block;
            padding: 0.8rem 1.8rem;
            background-color: #5c7cfa;
            color: white;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            margin: 0.5rem;
            position: relative;
            overflow: hidden;
        }
        
        .btn:hover {
            background-color: #4263eb;
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(66, 99, 235, 0.3);
        }

        .btn:active {
            transform: translateY(-1px);
        }
        
        .background-shapes {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
        }
        
        .shape {
            position: absolute;
            opacity: 0.2;
        }
        
        .circle {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background-color: #5c7cfa;
            top: 20%;
            left: 10%;
            animation: float 8s ease-in-out infinite;
        }
        
        .square {
            width: 100px;
            height: 100px;
            background-color: #ff922b;
            top: 60%;
            right: 15%;
            transform: rotate(45deg);
            animation: float 9s ease-in-out infinite 1s;
        }
        
        .triangle {
            width: 0;
            height: 0;
            border-left: 80px solid transparent;
            border-right: 80px solid transparent;
            border-bottom: 120px solid #51cf66;
            top: 30%;
            right: 5%;
            animation: float 10s ease-in-out infinite 2s;
        }
        
        .donut {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 25px solid #e64980;
            top: 75%;
            left: 20%;
            animation: float 11s ease-in-out infinite 3s;
        }
        
        @keyframes float {
            0% {
                transform: translateY(0) rotate(0deg);
            }
            50% {
                transform: translateY(-20px) rotate(5deg);
            }
            100% {
                transform: translateY(0) rotate(0deg);
            }
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 1.5rem;
            }
            
            .error-code {
                font-size: 8rem;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            p {
                font-size: 1rem;
            }
        }
        
        @media (max-width: 480px) {
            .error-code {
                font-size: 6rem;
            }
            
            h1 {
                font-size: 1.7rem;
            }
        }
    </style>
</head>
<body>
    <div class="background-shapes">
        <div class="shape circle"></div>
        <div class="shape square"></div>
        <div class="shape triangle"></div>
        <div class="shape donut"></div>
    </div>
    
    <div class="container">
        <div class="error-code">404</div>
        <h1>Halaman Tidak Ditemukan</h1>
        <p>Maaf, halaman yang Anda cari tidak dapat ditemukan. Halaman tersebut mungkin telah dipindahkan, dihapus, atau tidak pernah ada sama sekali.</p>
        <a href="login.php" class="btn">Kembali ke Beranda</a>
        <a href="javascript:history.back()" class="btn">Kembali ke Halaman Sebelumnya</a>
    </div>
</body>
</html>
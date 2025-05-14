<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }} - Gerador de Documentos</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
<<<<<<< HEAD
    @viteReactRefresh
    @vite(['resources/js/app.jsx', 'resources/css/app.css'])
=======
    <link rel="stylesheet" href="{{ asset('build/assets/app-Dy6ZoQ8W.css') }}">
    <script type="module" src="{{ asset('build/assets/app-Dvth8VWT.js') }}"></script>

>>>>>>> 42982285fb2f3768eade067e3517a013b5e7ddaf
    
</head>
<body class="font-sans antialiased bg-gray-100">
    <div id="app" class="min-h-screen"></div>
   
</body>
</html> 

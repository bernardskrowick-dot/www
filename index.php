<?php
$isLocal = true;
$folders = array_filter(glob('*'), 'is_dir');
sort($folders); // Tri alphabétique

// Détecte WordPress
function isWordPress($folder)
{
    return file_exists("$folder/wp-config.php");
}

// Couleur des boutons selon type de projet
function buttonColor($folder)
{
    return isWordPress($folder) ? '#28a745' : '#2c7be5';
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Mes sites locaux</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 20px 40px;
        }

        h1 {
            margin-bottom: 20px;
            font-size: 32px;
        }

        .container {
            max-width: 1200px;
            margin: auto;
        }

        .server-info {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 40px;
        }

        .server-info p {
            margin: 5px 0;
        }

        .info-button {
            display: inline-block;
            text-decoration: none;
            color: white;
            background: #17a2b8;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: bold;
            font-size: 12px;
            margin-left: 8px;
            transition: background 0.2s;
        }

        .info-button:hover {
            background: #117a8b;
        }

        #search {
            margin-bottom: 20px;
            padding: 6px;
            width: 100%;
            max-width: 300px;
            border-radius: 6px;
            border: 1px solid #ccc;
        }

        .projects {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }

        .project-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            width: 200px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
            text-align: center;
        }

        .project-card:hover {
            transform: translateY(-4px);
        }

        .project-card a.button {
            display: inline-block;
            text-decoration: none;
            color: white;
            padding: 10px 15px;
            border-radius: 6px;
            font-weight: bold;
            margin-bottom: 10px;
            transition: background 0.2s;
        }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            background: #ffb822;
            color: white;
            border-radius: 4px;
            font-size: 12px;
        }

        @media (max-width: 600px) {
            .projects {
                flex-direction: column;
            }

            .project-card {
                width: 100%;
            }
        }
    </style>
</head>

<body>

    <div class="container">

        <div class="server-info">
            <h1>🌐 Laragon Dashboard</h1>
            <?php if ($isLocal): ?>
                <p><?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'], ENT_QUOTES, 'UTF-8'); ?></p>
                <p>PHP version: <?= htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8'); ?>
                    <a class="info-button" title="phpinfo()" href="/phpinfo.php" target="_blank">info</a>
                </p>
                <p>Document Root: <?= htmlspecialchars($_SERVER['DOCUMENT_ROOT'], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php else: ?>
                <p>Server is running</p>
                <p>PHP is enabled</p>
            <?php endif; ?>
            <p>
                <a class="info-button" href="https://laragon.org/docs" target="_blank" rel="noopener">Getting
                    Started</a>
                <a class="info-button" href="http://localhost/phpmyadmin" target="_blank">phpMyAdmin</a>
            </p>
        </div>

        <h1>🚀 Mes projets locaux (<?= count($folders) ?>)</h1>

        <input type="text" id="search" placeholder="Rechercher un projet...">

        <div class="projects">
            <?php foreach ($folders as $folder): ?>
                <div class="project-card">
                    <a class="button" style="background: <?= buttonColor($folder) ?>" href="http://<?= $folder ?>.test"
                        target="_blank" rel="noopener">
                        <?= ucfirst($folder) ?>
                    </a>
                    <?php if (isWordPress($folder)): ?>
                        <div class="badge">WordPress</div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

    </div>

    <script>
        document.getElementById('search').addEventListener('input', function () {
            const filter = this.value.toLowerCase();
            document.querySelectorAll('.project-card').forEach(card => {
                const text = card.querySelector('a').textContent.toLowerCase();
                card.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    </script>

</body>

</html>
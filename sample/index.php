<?= (require __DIR__ . '/../Hoge.php')(function (Hoge\Framework $php) {
    $php->get('/', function () { ?>
<!DOCTYPE html>
<html>
<title>Hoge index</title>
<h1>The Hoge PHP Framework</h1>
</html>
    <?php });
    $php->get('/phpinfo', function () {
        phpinfo();
    });
}) ?>

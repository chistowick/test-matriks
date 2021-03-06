<?php

// Подключаем файл с функциями для генерации новых предложений
require_once(__DIR__ . '/app/functions.php');

// Подключаем класс для соединения с БД 
require_once(__DIR__ . '/app/Database.php');

// Подключаемся к базе данных с конфигурацией в файле по адресу
$dbh = Database::getConnection(__DIR__ . '/config/db.php');

define("PATH_TO_INPUT_FILE", __DIR__ . '/input/test_in.txt');
define("PATH_TO_OUTPUT_FILE", __DIR__ . '/output/test_out.txt');

// Если файл со входным текстом найден и доступен для чтения
if (is_readable(PATH_TO_INPUT_FILE)) {

    $inputTextString = trim(file_get_contents(PATH_TO_INPUT_FILE));
} else {

    // Если файл не найден или не доступен для чтения
    die('Файл со входными данными не найден');
}

/**
 * Патч для того, чтобы файл с исходным текстом, присланный в кодировке Windows-1251 тоже распознавался
 * 
 * Если не удалось строго определить кодировку как UTF-8
 */
if (!mb_detect_encoding($inputTextString, "UTF-8", TRUE)) {

    // Пробуем перевести Windows-1251 в UTF-8
    $inputTextString = mb_convert_encoding($inputTextString, 'UTF-8', 'Windows-1251');
}

// Массив слов и счетчик, используемый как для задания ключей
$words = array();
$wordsCounter = 0;

// Regexp - разделители текста на фразы
$pattern = "/[.!?;:]/u";

// Разбиваем текст на фразы (без пустых подстрок) и записываем их в массив для фраз
$phrases = array();
$phrases = preg_split($pattern, $inputTextString, 0, PREG_SPLIT_NO_EMPTY);

// Вычисляем количество фраз в тексте
define("PHRASES_COUNT", count($phrases));

// Regexp - разделители фраз на слова
$pattern_2 = "/[-,`'\"\s]/u";

// Перебираем все фразы по порядку
foreach ($phrases as $key => $phrase) {

    // Разбиваем каждую фразу по regexp $pattern_2 на отдельные слова, (без пустых подстрок)
    $parts = preg_split($pattern_2, $phrase, 0, PREG_SPLIT_NO_EMPTY);

    // Получаем ключ последнего слова во фразе
    $lastKey = array_key_last($parts);

    // echo "[$key] = " . "$phrase" . "<br>";

    // Наполняем основной массив словами и статистикой ($key_2 - ключи массива слов во фразе)
    foreach ($parts as $key_2 => $part) {

        /**
         * Ищем текущее слово в массиве $words, если оно уже в нем есть,
         * то статистику нужно собирать в уже существующий элемент
         * 
         * $marker - маркер того, что слово уже встречалось
         * $originalKey - ключ, оригинального слова - того слова, которое встретилось первым
         * */
        $marker = FALSE;
        $originalKey = FALSE;

        for ($m = 1; $m <= count($words); $m++) {
            if ($words[$m]['text'] == $part) {

                // Если слово есть в массиве, запоминаем ключ элемента и устанавливаем маркер
                $originalKey = $m;
                $marker = TRUE;
                break;
            }
        }

        // Если текущего слова нет в массиве слов, создаем элемент с уникальным ключом
        if ($marker === FALSE) {

            // Повышаем счетчик слов (т.е. создаем новый уникальный идентификатор для слова)
            $wordsCounter = $wordsCounter + 1;

            $words[$wordsCounter] = array(); // Массив статистики для конкретного слова
            $words[$wordsCounter]['firstCount'] = 0; // Начальное значаение счетчика первых появлений
            $words[$wordsCounter]['lastCount'] = 0; // Начальное значение счетчика последних появлений
            $words[$wordsCounter]['text'] = $part; // Текст самого слова
            $words[$wordsCounter]['numCount'] = 1; // Начальное значение счетчика появления слова в тексте
            $words[$wordsCounter]['next_is_count'] = array(); // Массив счетчиков появления следующих слов
            $words[$wordsCounter]['probability_next_is'] = array(); // Массив вероятностей появления следующих слов
            $words[$wordsCounter]['firstProbability'] = 0; // Вероятность, что слово первое (начальное значение)
            $words[$wordsCounter]['lastProbability'] = 0; // Вероятность, что слово последнее (начальное значение)

            // Если слово первое в массиве, увеличиваем счетчик первых вхождений
            if ($key_2 == 0) {
                $words[$wordsCounter]['firstCount'] = 1;
            }

            // Если слово последнее в массиве, увеличиваем счетчик последних вхождений
            if ($key_2 == $lastKey) {
                $words[$wordsCounter]['lastCount'] = 1;
            }

            // Для всех слов во фразе начиная со второго: 
            // устанавливаем предыдущему слову количество появлений текущего слова после него
            if ($key_2 != 0) {

                $words[$beforeKey]['next_is_count'][$wordsCounter] = 1;
            }

            // После всех манипуляций запоминаем ключ-идентификатор текущего слова,
            // как $beforeKey для работы со следующим словом
            $beforeKey = $wordsCounter;
        } else {
            // Если слово уже есть в массиве - добавляем единицу к счетчику появления слова
            $words[$originalKey]['numCount']++;

            // Для первых слов во фразе увеличиваем счетчик первых вхождений
            if ($key_2 == 0) {
                $words[$originalKey]['firstCount']++;
            }

            // Для последних слов фразы увеличиваем счетчик последних вхождений
            if ($key_2 == $lastKey) {
                $words[$originalKey]['lastCount']++;
            }

            // Для всех слов во фразе начиная со второго, 
            // устанавливаем предыдущему слову количество появлений текущего слова после него
            if ($key_2 != 0) {

                // Если это слово уже встречалось после предыдущего
                if (isset($words[$beforeKey]['next_is_count'][$originalKey])) {

                    // Увеличиваем соответствующий счетчик у предыдущего слова
                    $words[$beforeKey]['next_is_count'][$originalKey]++;
                } else {
                    // Если слово впервые встречается после предыдущего, задаем ему стартовое значение
                    $words[$beforeKey]['next_is_count'][$originalKey] = 1;
                }
            }

            // После всех манипуляций запоминаем ключ-идентификатор текущего слова,
            // как $beforeKey для следующего слова
            $beforeKey = $originalKey;
        }
    }
}

// Вычисляем вероятности
for ($l = 1; $l <= count($words); $l++) {

    // Находим вероятность того, что слово первое во фразе
    $words[$l]['firstProbability'] = ($words[$l]['firstCount'] ?: 0) / PHRASES_COUNT;

    // Находим вероятность того, что слово последнее во фразе
    $words[$l]['lastProbability'] = ($words[$l]['lastCount'] ?: 0) / PHRASES_COUNT;

    // Находим, с какой вероятностью после этого слова идут те или иные слова (для которых существуют счетчики)
    foreach ($words[$l]['next_is_count'] as $key_5 => $next_is_count) {

        // Вероятность равна отношению количества появления слова-2 после слова-1
        // к общему количеству НЕпоследних появлений слова-1 в тексте
        $words[$l]['probability_next_is'][$key_5] = ($next_is_count / ($words[$l]['numCount'] - $words[$l]['lastCount']));
    }
}

// Задаем значение количества фраз в тексте
define("WORDS_COUNT", count($words));

$tableName = 'words_probabilities_' . date("H_i_s");

// Формируем запрос на создание таблицы
$sql = "CREATE TABLE IF NOT EXISTS `$tableName` ( ";
$sql .= "`id` INT(10) NOT NULL , ";
$sql .= "`text` VARCHAR(255) NOT NULL , ";
$sql .= "`firstProbability` FLOAT(20) UNSIGNED NULL DEFAULT NULL, ";
$sql .= "`lastProbability` FLOAT(20) UNSIGNED NULL DEFAULT NULL, ";

// Формируем столбцы по количеству слов в массиве
for ($i = 1; $i <= WORDS_COUNT; $i++) {
    $sql .= "`$i` FLOAT(20) UNSIGNED NOT NULL DEFAULT '0', ";
}

$sql .= "PRIMARY KEY (id)) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = MyISAM";

// Выполняем создание таблицы
if ($pdostmt = $dbh->query($sql)) {

    echo "Таблица $tableName создана успешно";
}

$sql = "INSERT INTO `$tableName`";
$sql .= " (`id`, `text`, `firstProbability`, `lastProbability`";

// Формируем названия столбцов
for ($i = 1; $i <= WORDS_COUNT; $i++) {
    $sql .= ", `$i`";
}

$sql .= ") VALUES ";

// Формируем плейсхолдеры для значений вероятностей
$sql .= "(?, ?, ?, ?";

for ($j = 1; $j <= WORDS_COUNT; $j++) {

    $sql .= ", ?";
}

$sql .= ")";

// Подготавливаем выражение
$pdostmt = $dbh->prepare($sql);

// Задаем значение плейсхолдерам для каждого слова и выполняем запрос
for ($i = 1; $i <= WORDS_COUNT; $i++) {

    $pdostmt->bindParam(1, $i);
    $pdostmt->bindParam(2, $words[$i]['text']);
    $pdostmt->bindParam(3, $words[$i]['firstProbability'], PDO::PARAM_STR);
    $pdostmt->bindParam(4, $words[$i]['lastProbability'], PDO::PARAM_STR);

    // Если для слова массив вероятностей появления следующих слов пуст
    if (!$words[$i]['probability_next_is']) {

        // то все значения оставшихся полей равны 0
        for ($j = 5, $k = 1; $k <= WORDS_COUNT; $j++, $k++) {
            $val = 0.0;
            $pdostmt->bindParam($j, $val, PDO::PARAM_STR);
        }
    } else {

        // Иначе к каждому плейсхолдеру привязываем значение соответствующей вероятности 
        // или ноль, если элемент отсутствует
        // $j - номер плейсхолдера
        for ($j = 5, $k = 1; $k <= WORDS_COUNT; $j++, $k++) {

            if (isset($words[$i]['probability_next_is'][$k])) {
                $pdostmt->bindParam($j, $words[$i]['probability_next_is'][$k], PDO::PARAM_STR);
            } else {
                $val = 0.0;
                $pdostmt->bindParam($j, $val, PDO::PARAM_STR);
            }
        }
    }

    // Выполняем вставку
    $pdostmt->execute();
}

// Получаем данные из базы
// Формируем запрос
$sql = "SELECT * FROM `$tableName`";

// Выполняем запрос
$pdostmt = $dbh->query($sql);

$f = 1;
// Извлечение всех строк результирующего набора
while ($row = $pdostmt->fetch(PDO::FETCH_ASSOC)) {

    $probabilityTable[$f] = $row;
    // var_dump($probabilityTable[$f]);

    $f++;
}

// var_dump($probabilityTable);

// Определяем переменную, в которую будем записывать текст перед записью в файл
$out = '';

// Формируем 30 фраз в строку
for ($i = 1; $i <= 20; $i++) {
    // Определяем первое слово
    $firstWordId = defineFirstWordId($probabilityTable);

    // Записываем первое слово в строку вывода
    $out .= $probabilityTable[$firstWordId]['text'];

    // Считаем первое слово текущим
    $curentWordId = $firstWordId;

    // Пока проверка текущего слова не вернет TRUE (т.е. слово последнее)
    while (!checkLastOrNotById($probabilityTable, $curentWordId)) {

        // Получаем id следующего слова
        $nextWordId = getNextWordId($probabilityTable, $curentWordId);

        // Если getNextWordId вернул FALSE т.е. слово не определено статистически как последнее
        // в функции checkLastOrNotById, однако после него никакие слова во фразах не встречались
        // выходим из while
        if ($nextWordId === FALSE) {

            break;
        }

        // Добавляем следующее слово к строке вывода
        $out .= " " . $probabilityTable[$nextWordId]['text'];

        // Теперь  следующее слово становится текущим словом и идет на проверку в while
        $curentWordId = $nextWordId;
    }

    // Когда while закончился, ставим точку и конец строки
    $out .= "." . PHP_EOL;
}

if (file_put_contents(PATH_TO_OUTPUT_FILE, $out)) {

    echo "<br>";
    echo "Файл с новым текстом готов";
}

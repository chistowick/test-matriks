<?php

require_once(__DIR__ . '/app/Database.php');

define("PATH_TO_INPUT_FILE", __DIR__ . '/input/test_in.txt');

// Если файл со входным текстом найден и доступен для чтения
if (is_readable(PATH_TO_INPUT_FILE)) {

    $inputTextString = file_get_contents(PATH_TO_INPUT_FILE);
} else {

    // Если файл не найден или не доступен для чтения
    die('Файл со входными данными не найден');
}

// Подключаемся к базе данных
$dbh = Database::getConnection(__DIR__ . '/config/db.php');

/**
 * Патч для того, чтобы файл с исходным текстом, присланный в кодировке Windows-1251 тоже распознавался
 * 
 * Если не удалось строго определить кодировку как UTF-8
 */
if (!mb_detect_encoding($inputTextString, "UTF-8", TRUE)) {

    // Пробуем перевести Windows-1251 в UTF-8
    $inputTextString = mb_convert_encoding($inputTextString, 'UTF-8', 'Windows-1251');
}

echo $inputTextString;
echo "<hr>";

$words = array();
$wordsCounter = 0;

// Regexp - разделители текста на фразы
$pattern = "/[.!?;:]/u";

// Разбиваем текст на фразы (без пустых подстрок) и записываем их в массив
$phrases = array();
$phrases = preg_split($pattern, $inputTextString, 0, PREG_SPLIT_NO_EMPTY);

// Задаем значение количества фраз в тексте
define("PHRASES_COUNT", count($phrases));

// Regexp - разделители фраз на слова
$pattern_2 = "/[-,`'\"\s]/u";

// Перебираем все фразы по порядку
foreach ($phrases as $key => $phrase) {

    // Разбиваем каждую фразу по regexp $pattern_2
    $parts = preg_split($pattern_2, $phrase, 0, PREG_SPLIT_NO_EMPTY);

    // Получаем значение последнего ключа
    $lastKey = array_key_last($parts);

    // echo "[$key] = " . "$phrase" . "<br>";

    // Наполняем основной массив словами и статистикой ($key_2 - ключи массива слов во фразе)
    foreach ($parts as $key_2 => $part) {

        // Ищем текущее слово в массиве $words
        $marker = FALSE;
        $originalKey = FALSE;

        foreach ($words as $key_3 => $word) {
            if ($word['text'] == $part) {

                // Если слово есть в массиве, запоминаем ключ элемента и устанавливаем маркер
                $originalKey = $key_3;
                $marker = TRUE;
                break;
            }
        }

        // Если текущего слова нет в массиве слов, создаем элемент с уникальным ключом
        if ($marker === FALSE) {

            // Повышаем счетчик слов (т.е. создаем новый уникальный идентификатор для слова)
            $wordsCounter = $wordsCounter + 1;

            $words[$wordsCounter] = array();
            $words[$wordsCounter]['firstCount'] = 0; // Начальное значаение счетчика первых появлений
            $words[$wordsCounter]['lastCount'] = 0; // Начальное значение счетчика последних появлений
            $words[$wordsCounter]['text'] = $part; // Текст самого слова
            $words[$wordsCounter]['numCount'] = 1; // Начальное значение счетчика появления слова в тексте

            // Если слово первое в массиве, увеличиваем счетчик первых вхождений
            if ($key_2 == 0) {
                $words[$wordsCounter]['firstCount'] = 1;
            }

            // Если слово последнее в массиве, увеличиваем счетчик последних вхождений
            if ($key_2 == $lastKey) {
                $words[$wordsCounter]['lastCount'] = 1;
            }

            // Для всех слов во фразе начиная со второго, 
            // устанавливаем предыдущему слову количество появлений текущего слова после него
            if ($key_2 != 0) {

                $words[$beforeKey]['next_is_count'][$wordsCounter] = 1;
            }

            // После всех манипуляций запоминаем ключ-идентификатор текущего слова,
            // как $beforeKey для следующего слова
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


foreach ($words as $key_4 => $word) {

    // Находим вероятность того, что слово первое во фразе
    $word['firsProbability'] = ($word['firstCount'] ?: 0) / PHRASES_COUNT;

    // Находим вероятность того, что слово последнее во фразе
    $word['lastProbability'] = ($word['lastCount'] ?: 0) / PHRASES_COUNT;

    // Находим, с какой вероятностью после этого слова идут те или иные слова
    foreach($word['next_is_count'] as $key_5 => $next_is_count){

        // Вероятность равна отношению количества появления слова-2 после слова-1
        // к общему количеству НЕпоследних появлений слова-1 в тексте
        $word['probability_next_is'][$key_5] = $next_is_count / ($word['numCount'] - $word['lastCount']);

    }

    echo '<hr>' . $key_4 . '<br>';
    var_dump($word);
}

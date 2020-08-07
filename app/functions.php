<?php

// Функции:

/**
 * Возвращает id первого слова
 */
function defineFirstWordId(array $tableProb)
{
    // Вычисляем сколько в массиве всего слов
    $count = count($tableProb);

    // Задаем множитель для удобства расчетов
    $mod = 1000000;

    $total = 0;

    // Определяем сумму всех отрезков
    for ($i = 1; $i <= $count; $i++) {

        // Для упрощения расчетов, увеличиваем значение вероятности
        $total += +$tableProb[$i]['firstProbability'] * $mod;
    }

    // Генерируем случайную точку на суммарном отрезке
    if ($total >= 2) {

        $dot = rand(1, $total);
    } else {
        // Если $total меньше 2, то считаем, что вероятность слишком мала, 
        // и значит где-то ошибка в вычислении вероятностей
        return FALSE;
    }

    $level = 0;

    // Определяем в какой промежуток попала точка
    for ($i = 1; $i <= $count; $i++) {

        $level += +$tableProb[$i]['firstProbability'] * $mod;

        // Как только точка попадет в нужный интервал, возвращаем $i = id соответсвующего слова
        if ($dot <= $level) {
            return $i;
        }
    }

    // Если вдруг ничего не найдено, явно возвращаем false
    return FALSE;
}

/**
 * Определяет по id последнее ли это слово
 * 
 * TRUE - Слово последнее
 * FALSE - Продолжаем дальше
 */
function checkLastOrNotById(array $tableProb, int $id)
{
    // Задаем коэффициент для удобства расчетов
    $mod = 1000000;

    // Получаем значение lastProbability слова по его id
    $lastProb = +$tableProb[$id]['lastProbability'];

    // Генерируем число от 1/$mod до 1
    $dot = rand(1, $mod) / $mod;

    // Сравниваем $dot со значением вероятности, что слово последнее
    if ($dot <= $lastProb) {

        // Если точка попала в интервал, возвращаем TRUE (слово последнее!)
        return TRUE;
    } else {
        // Иначе возвращаем FALSE
        return FALSE;
    }
}

/**
 * Возвращает id следующего слова
 */
function getNextWordId(array $tableProb, int $currentId)
{
    // Вычисляем сколько в массиве элементов с вероятностями следования других слов
    $count = count($tableProb[$currentId]) - 4; // -4 чтобы убрать поля id, text, lastProbability, firstProbability

    // Задаем множитель для удобства расчетов
    $mod = 1000000;

    $total = 0;

    // Определяем сумму всех отрезков
    for ($i = 1; $i <= $count; $i++) {
        // Для упрощения расчетов, увеличиваем значение вероятности
        $total += +$tableProb[$currentId][$i] * $mod;
    }

    // Генерируем случайную точку на суммарном отрезке
    if ($total >= 2) {

        $dot = rand(1, $total);
    } else {
        // Если $total меньше 2, то считаем, что вероятность слишком мала, 
        // и значит где-то ошибка в вычислении вероятностей
        return FALSE;
    }

    $level = 0;

    // Определяем в какой промежуток попала точка
    for ($i = 1; $i <= $count; $i++) {

        $level += +$tableProb[$currentId][$i] * $mod;

        // Как только точка попадет в нужный интервал, возвращаем $i равный id следующего слова
        if ($dot <= $level) {
            return $i;
        }
    }

    // Если вдруг ничего не найдено, явно возвращаем false
    return FALSE;
}

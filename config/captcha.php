<?php

declare(strict_types=1);

/**
 * Captcha question pool — exactly 50 questions.
 *
 * Each entry: ['q' => string, 'a' => string]
 * All answers are lowercase strings with no trailing whitespace.
 *
 * Distribution: 20 arithmetic · 20 logic/comparison · 10 general knowledge.
 *
 * @return array<int, array{q: string, a: string}>
 */
return [
    // Arithmetic (0–19)
    ['q' => 'What is 3 + 4?', 'a' => '7'],
    ['q' => 'What is 7 + 5?', 'a' => '12'],
    ['q' => 'What is 10 - 3?', 'a' => '7'],
    ['q' => 'What is 15 - 8?', 'a' => '7'],
    ['q' => 'What is 4 × 3?', 'a' => '12'],
    ['q' => 'What is 6 × 7?', 'a' => '42'],
    ['q' => 'What is 20 ÷ 4?', 'a' => '5'],
    ['q' => 'What is 18 ÷ 6?', 'a' => '3'],
    ['q' => 'What is 9 + 8?', 'a' => '17'],
    ['q' => 'What is 13 - 6?', 'a' => '7'],
    ['q' => 'What is 5 × 5?', 'a' => '25'],
    ['q' => 'What is 100 ÷ 10?', 'a' => '10'],
    ['q' => 'What is 11 + 11?', 'a' => '22'],
    ['q' => 'What is 30 - 14?', 'a' => '16'],
    ['q' => 'What is 8 × 4?', 'a' => '32'],
    ['q' => 'What is 49 ÷ 7?', 'a' => '7'],
    ['q' => 'What is 6 + 9?', 'a' => '15'],
    ['q' => 'What is 25 - 9?', 'a' => '16'],
    ['q' => 'What is 3 × 9?', 'a' => '27'],
    ['q' => 'What is 36 ÷ 6?', 'a' => '6'],

    // Logic / comparison (20–39)
    ['q' => 'Which is larger: 4 or 9?', 'a' => '9'],
    ['q' => 'Which is smaller: 3 or 7?', 'a' => '3'],
    ['q' => 'Which is larger: 15 or 12?', 'a' => '15'],
    ['q' => 'Which is smaller: 100 or 99?', 'a' => '99'],
    ['q' => 'Which is larger: 8 or 2?', 'a' => '8'],
    ['q' => 'Is 5 greater than 10? Answer yes or no.', 'a' => 'no'],
    ['q' => 'Is 20 greater than 15? Answer yes or no.', 'a' => 'yes'],
    ['q' => 'Is 7 equal to 7? Answer yes or no.', 'a' => 'yes'],
    ['q' => 'Is 0 less than 1? Answer yes or no.', 'a' => 'yes'],
    ['q' => 'Which is larger: 50 or 49?', 'a' => '50'],
    ['q' => 'If you have 10 apples and eat 3, how many remain?', 'a' => '7'],
    ['q' => 'If a dozen eggs is 12, how many are half a dozen?', 'a' => '6'],
    ['q' => 'Which comes first alphabetically: cat or dog?', 'a' => 'cat'],
    ['q' => 'Which comes last alphabetically: apple, mango, or banana?', 'a' => 'mango'],
    ['q' => 'If it takes 2 minutes to boil one egg, how many minutes to boil 2 eggs at once?', 'a' => '2'],
    ['q' => 'A square has how many sides?', 'a' => '4'],
    ['q' => 'A triangle has how many corners?', 'a' => '3'],
    ['q' => 'How many sides does a hexagon have?', 'a' => '6'],
    ['q' => 'Which is larger: 1000 or 999?', 'a' => '1000'],
    ['q' => 'Is 4 an even number? Answer yes or no.', 'a' => 'yes'],

    // General knowledge (40–49)
    ['q' => 'How many days are in a week?', 'a' => '7'],
    ['q' => 'How many months are in a year?', 'a' => '12'],
    ['q' => 'How many hours are in a day?', 'a' => '24'],
    ['q' => 'How many minutes are in an hour?', 'a' => '60'],
    ['q' => 'How many seconds are in a minute?', 'a' => '60'],
    ['q' => 'How many days are in a non-leap year?', 'a' => '365'],
    ['q' => 'How many centimetres are in a metre?', 'a' => '100'],
    ['q' => 'How many primary colours are there?', 'a' => '3'],
    ['q' => 'How many continents are there?', 'a' => '7'],
    ['q' => 'How many letters are in the English alphabet?', 'a' => '26'],
];

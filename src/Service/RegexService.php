<?php

namespace App\Service;

class RegexService
{
	/**
	 * Вернет результат поиска данных по регулярному выражению
	 *
	 * @param string $pattern
	 * @param string $subject
	 * @return array
	 */
	public static function regexMatches(string $pattern, string $subject): array {
		return preg_match($pattern, $subject, $matches) ? $matches : [];
	}
}
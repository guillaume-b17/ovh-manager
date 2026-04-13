<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Remplace les variables dans les modèles de message répondeur.
 *
 * Variables : {date_debut}, {date_fin}, {telephone_agence}
 */
final class ResponderTemplateInterpolator
{
    public function interpolate(
        string $template,
        ?\DateTimeImmutable $from,
        ?\DateTimeImmutable $to,
        string $agencyPhone = '',
    ): string {
        $replacements = [
            '{date_debut}' => null !== $from ? $from->format('d/m/Y \à H:i') : '—',
            '{date_fin}' => null !== $to ? $to->format('d/m/Y \à H:i') : '—',
            '{telephone_agence}' => '' !== trim($agencyPhone) ? trim($agencyPhone) : '—',
        ];

        return strtr($template, $replacements);
    }
}

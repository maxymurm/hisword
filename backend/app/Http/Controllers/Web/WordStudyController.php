<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WordStudyController extends Controller
{
    /**
     * Get word study data for a Strong's number or word.
     */
    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'word' => ['required', 'string', 'max:100'],
            'strongs' => ['sometimes', 'nullable', 'string', 'max:20'],
            'module' => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);

        $word = $validated['word'];
        $strongs = $validated['strongs'] ?? null;

        // Build word study data
        $data = [
            'word' => $word,
            'strongs' => $strongs,
            'definition' => $this->getDefinition($strongs ?? $word),
            'occurrences' => $this->getOccurrences($word),
            'related_words' => $this->getRelatedWords($strongs),
            'cross_references' => $this->getCrossReferences($word),
        ];

        return response()->json($data);
    }

    private function getDefinition(?string $key): array
    {
        // Sample Strong's definitions – in production this would query the SWORD lexicon modules
        $definitions = [
            'H157' => [
                'strongs' => 'H157',
                'original' => 'אָהַב',
                'transliteration' => 'ʼâhab',
                'pronunciation' => 'aw-hab\'',
                'part_of_speech' => 'verb',
                'definition' => 'To have affection for (sexually or otherwise); to like; to love.',
                'kjv_usage' => 'love (169x), lover (16x), friend (9x), beloved (5x), liketh (1x)',
            ],
            'G26' => [
                'strongs' => 'G26',
                'original' => 'ἀγάπη',
                'transliteration' => 'agápē',
                'pronunciation' => 'ag-ah\'-pay',
                'part_of_speech' => 'noun feminine',
                'definition' => 'Love, i.e. affection or benevolence. The highest form of love, especially charity.',
                'kjv_usage' => 'love (86x), charity (27x), dear (1x), charitably (1x), feast of charity (1x)',
            ],
            'G4102' => [
                'strongs' => 'G4102',
                'original' => 'πίστις',
                'transliteration' => 'pístis',
                'pronunciation' => 'pis\'-tis',
                'part_of_speech' => 'noun feminine',
                'definition' => 'Persuasion, i.e. credence; moral conviction of religious truth.',
                'kjv_usage' => 'faith (239x), assurance (1x), belief (1x), believe (1x), fidelity (1x)',
            ],
            'G5485' => [
                'strongs' => 'G5485',
                'original' => 'χάρις',
                'transliteration' => 'cháris',
                'pronunciation' => 'khar\'-ece',
                'part_of_speech' => 'noun feminine',
                'definition' => 'Graciousness; the divine influence upon the heart, and its reflection in the life.',
                'kjv_usage' => 'grace (130x), favour (6x), thanks (4x), thank (4x), pleasure (2x)',
            ],
        ];

        return $definitions[$key] ?? [
            'strongs' => $key,
            'original' => $key ?? '',
            'transliteration' => '',
            'pronunciation' => '',
            'part_of_speech' => '',
            'definition' => 'Definition data will be available when lexicon modules are installed.',
            'kjv_usage' => '',
        ];
    }

    private function getOccurrences(string $word): array
    {
        // Sample occurrence data
        return [
            'total' => 310,
            'ot' => 184,
            'nt' => 126,
            'by_book' => [
                ['book' => 'Psalms', 'count' => 48],
                ['book' => '1 John', 'count' => 27],
                ['book' => 'Song of Solomon', 'count' => 21],
                ['book' => 'John', 'count' => 18],
                ['book' => '1 Corinthians', 'count' => 16],
                ['book' => 'Romans', 'count' => 14],
                ['book' => 'Deuteronomy', 'count' => 13],
                ['book' => 'Proverbs', 'count' => 12],
            ],
        ];
    }

    private function getRelatedWords(?string $strongs): array
    {
        if (!$strongs) return [];

        return [
            ['strongs' => 'G5368', 'word' => 'φιλέω (phileō)', 'meaning' => 'Brotherly love, friendship'],
            ['strongs' => 'G2309', 'word' => 'θέλω (thelō)', 'meaning' => 'To will, to desire'],
            ['strongs' => 'H2617', 'word' => 'חֶסֶד (chesed)', 'meaning' => 'Lovingkindness, covenant love'],
        ];
    }

    private function getCrossReferences(string $word): array
    {
        return [
            ['reference' => '1 Corinthians 13:4-7', 'text' => 'Love is patient, love is kind...'],
            ['reference' => '1 John 4:8', 'text' => 'God is love.'],
            ['reference' => 'John 3:16', 'text' => 'For God so loved the world...'],
            ['reference' => 'Romans 5:8', 'text' => 'God demonstrates his own love for us...'],
            ['reference' => 'Deuteronomy 6:5', 'text' => 'Love the Lord your God with all your heart...'],
        ];
    }
}

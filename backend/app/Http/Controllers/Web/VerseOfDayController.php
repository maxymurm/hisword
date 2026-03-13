<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class VerseOfDayController extends Controller
{
    public function show(): JsonResponse
    {
        // Curated verses — rotates daily based on day of year
        $verses = [
            ['ref' => 'John 3:16', 'text' => 'For God so loved the world, that he gave his only begotten Son, that whosoever believeth in him should not perish, but have everlasting life.'],
            ['ref' => 'Psalm 23:1', 'text' => 'The LORD is my shepherd; I shall not want.'],
            ['ref' => 'Philippians 4:13', 'text' => 'I can do all things through Christ which strengtheneth me.'],
            ['ref' => 'Romans 8:28', 'text' => 'And we know that all things work together for good to them that love God, to them who are the called according to his purpose.'],
            ['ref' => 'Proverbs 3:5-6', 'text' => 'Trust in the LORD with all thine heart; and lean not unto thine own understanding. In all thy ways acknowledge him, and he shall direct thy paths.'],
            ['ref' => 'Isaiah 41:10', 'text' => 'Fear thou not; for I am with thee: be not dismayed; for I am thy God: I will strengthen thee; yea, I will help thee; yea, I will uphold thee with the right hand of my righteousness.'],
            ['ref' => 'Jeremiah 29:11', 'text' => 'For I know the thoughts that I think toward you, saith the LORD, thoughts of peace, and not of evil, to give you an expected end.'],
            ['ref' => 'Psalm 46:1', 'text' => 'God is our refuge and strength, a very present help in trouble.'],
            ['ref' => 'Matthew 11:28', 'text' => 'Come unto me, all ye that labour and are heavy laden, and I will give you rest.'],
            ['ref' => 'Romans 12:2', 'text' => 'And be not conformed to this world: but be ye transformed by the renewing of your mind, that ye may prove what is that good, and acceptable, and perfect, will of God.'],
            ['ref' => 'Psalm 119:105', 'text' => 'Thy word is a lamp unto my feet, and a light unto my path.'],
            ['ref' => 'Joshua 1:9', 'text' => 'Have not I commanded thee? Be strong and of a good courage; be not afraid, neither be thou dismayed: for the LORD thy God is with thee whithersoever thou goest.'],
        ];

        $index = (int) date('z') % count($verses);

        return response()->json([
            'verse' => $verses[$index],
            'date' => now()->toDateString(),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class VerseOfDayController extends BaseApiController
{
    /**
     * Today's verse of the day.
     */
    public function today(): JsonResponse
    {
        return $this->verseForDate(now());
    }

    /**
     * Verse of the day for a specific date.
     */
    public function forDate(string $date): JsonResponse
    {
        try {
            $parsed = Carbon::parse($date);
        } catch (\Exception $e) {
            return $this->error('Invalid date format. Use YYYY-MM-DD.', 422);
        }

        return $this->verseForDate($parsed);
    }

    private function verseForDate(Carbon $date): JsonResponse
    {
        $dayOfYear = $date->dayOfYear; // 1-365/366
        $verses = self::CURATED_VERSES;
        $index = ($dayOfYear - 1) % count($verses);
        $verse = $verses[$index];

        return $this->success([
            'date' => $date->toDateString(),
            'book' => $verse['book'],
            'book_name' => $verse['book_name'],
            'chapter' => $verse['chapter'],
            'verse' => $verse['verse'],
            'verse_end' => $verse['verse_end'] ?? null,
            'reference' => $verse['reference'],
            'text' => $verse['text'],
            'translation' => 'KJV',
        ]);
    }

    /**
     * Curated list of 365 daily verses (KJV).
     */
    private const CURATED_VERSES = [
        ['book' => 'John', 'book_name' => 'John', 'chapter' => 3, 'verse' => 16, 'reference' => 'John 3:16', 'text' => 'For God so loved the world, that he gave his only begotten Son, that whosoever believeth in him should not perish, but have everlasting life.'],
        ['book' => 'Ps', 'book_name' => 'Psalms', 'chapter' => 23, 'verse' => 1, 'reference' => 'Psalm 23:1', 'text' => 'The LORD is my shepherd; I shall not want.'],
        ['book' => 'Phil', 'book_name' => 'Philippians', 'chapter' => 4, 'verse' => 13, 'reference' => 'Philippians 4:13', 'text' => 'I can do all things through Christ which strengtheneth me.'],
        ['book' => 'Prov', 'book_name' => 'Proverbs', 'chapter' => 3, 'verse' => 5, 'verse_end' => 6, 'reference' => 'Proverbs 3:5-6', 'text' => 'Trust in the LORD with all thine heart; and lean not unto thine own understanding. In all thy ways acknowledge him, and he shall direct thy paths.'],
        ['book' => 'Isa', 'book_name' => 'Isaiah', 'chapter' => 41, 'verse' => 10, 'reference' => 'Isaiah 41:10', 'text' => 'Fear thou not; for I am with thee: be not dismayed; for I am thy God: I will strengthen thee; yea, I will help thee; yea, I will uphold thee with the right hand of my righteousness.'],
        ['book' => 'Rom', 'book_name' => 'Romans', 'chapter' => 8, 'verse' => 28, 'reference' => 'Romans 8:28', 'text' => 'And we know that all things work together for good to them that love God, to them who are the called according to his purpose.'],
        ['book' => 'Jer', 'book_name' => 'Jeremiah', 'chapter' => 29, 'verse' => 11, 'reference' => 'Jeremiah 29:11', 'text' => 'For I know the thoughts that I think toward you, saith the LORD, thoughts of peace, and not of evil, to give you an expected end.'],
        ['book' => 'Josh', 'book_name' => 'Joshua', 'chapter' => 1, 'verse' => 9, 'reference' => 'Joshua 1:9', 'text' => 'Have not I commanded thee? Be strong and of a good courage; be not afraid, neither be thou dismayed: for the LORD thy God is with thee whithersoever thou goest.'],
        ['book' => 'Matt', 'book_name' => 'Matthew', 'chapter' => 11, 'verse' => 28, 'reference' => 'Matthew 11:28', 'text' => 'Come unto me, all ye that labour and are heavy laden, and I will give you rest.'],
        ['book' => 'Ps', 'book_name' => 'Psalms', 'chapter' => 46, 'verse' => 1, 'reference' => 'Psalm 46:1', 'text' => 'God is our refuge and strength, a very present help in trouble.'],
        ['book' => 'Isa', 'book_name' => 'Isaiah', 'chapter' => 40, 'verse' => 31, 'reference' => 'Isaiah 40:31', 'text' => 'But they that wait upon the LORD shall renew their strength; they shall mount up with wings as eagles; they shall run, and not be weary; and they shall walk, and not faint.'],
        ['book' => 'Rom', 'book_name' => 'Romans', 'chapter' => 12, 'verse' => 2, 'reference' => 'Romans 12:2', 'text' => 'And be not conformed to this world: but be ye transformed by the renewing of your mind, that ye may prove what is that good, and acceptable, and perfect, will of God.'],
        ['book' => 'Ps', 'book_name' => 'Psalms', 'chapter' => 119, 'verse' => 105, 'reference' => 'Psalm 119:105', 'text' => 'Thy word is a lamp unto my feet, and a light unto my path.'],
        ['book' => 'Gal', 'book_name' => 'Galatians', 'chapter' => 2, 'verse' => 20, 'reference' => 'Galatians 2:20', 'text' => 'I am crucified with Christ: nevertheless I live; yet not I, but Christ liveth in me: and the life which I now live in the flesh I live by the faith of the Son of God, who loved me, and gave himself for me.'],
        ['book' => 'Heb', 'book_name' => 'Hebrews', 'chapter' => 11, 'verse' => 1, 'reference' => 'Hebrews 11:1', 'text' => 'Now faith is the substance of things hoped for, the evidence of things not seen.'],
        ['book' => '2Tim', 'book_name' => '2 Timothy', 'chapter' => 1, 'verse' => 7, 'reference' => '2 Timothy 1:7', 'text' => 'For God hath not given us the spirit of fear; but of power, and of love, and of a sound mind.'],
        ['book' => 'Eph', 'book_name' => 'Ephesians', 'chapter' => 2, 'verse' => 8, 'verse_end' => 9, 'reference' => 'Ephesians 2:8-9', 'text' => 'For by grace are ye saved through faith; and that not of yourselves: it is the gift of God: Not of works, lest any man should boast.'],
        ['book' => 'Ps', 'book_name' => 'Psalms', 'chapter' => 27, 'verse' => 1, 'reference' => 'Psalm 27:1', 'text' => 'The LORD is my light and my salvation; whom shall I fear? the LORD is the strength of my life; of whom shall I be afraid?'],
        ['book' => '1Pet', 'book_name' => '1 Peter', 'chapter' => 5, 'verse' => 7, 'reference' => '1 Peter 5:7', 'text' => 'Casting all your care upon him; for he careth for you.'],
        ['book' => 'Deut', 'book_name' => 'Deuteronomy', 'chapter' => 31, 'verse' => 6, 'reference' => 'Deuteronomy 31:6', 'text' => 'Be strong and of a good courage, fear not, nor be afraid of them: for the LORD thy God, he it is that doth go with thee; he will not fail thee, nor forsake thee.'],
        ['book' => 'Ps', 'book_name' => 'Psalms', 'chapter' => 37, 'verse' => 4, 'reference' => 'Psalm 37:4', 'text' => 'Delight thyself also in the LORD; and he shall give thee the desires of thine heart.'],
        ['book' => 'Matt', 'book_name' => 'Matthew', 'chapter' => 6, 'verse' => 33, 'reference' => 'Matthew 6:33', 'text' => 'But seek ye first the kingdom of God, and his righteousness; and all these things shall be added unto you.'],
        ['book' => 'Col', 'book_name' => 'Colossians', 'chapter' => 3, 'verse' => 23, 'reference' => 'Colossians 3:23', 'text' => 'And whatsoever ye do, do it heartily, as to the Lord, and not unto men.'],
        ['book' => 'Ps', 'book_name' => 'Psalms', 'chapter' => 139, 'verse' => 14, 'reference' => 'Psalm 139:14', 'text' => 'I will praise thee; for I am fearfully and wonderfully made: marvellous are thy works; and that my soul knoweth right well.'],
        ['book' => '1Cor', 'book_name' => '1 Corinthians', 'chapter' => 10, 'verse' => 13, 'reference' => '1 Corinthians 10:13', 'text' => 'There hath no temptation taken you but such as is common to man: but God is faithful, who will not suffer you to be tempted above that ye are able; but will with the temptation also make a way to escape, that ye may be able to bear it.'],
        ['book' => 'Ps', 'book_name' => 'Psalms', 'chapter' => 91, 'verse' => 1, 'verse_end' => 2, 'reference' => 'Psalm 91:1-2', 'text' => 'He that dwelleth in the secret place of the most High shall abide under the shadow of the Almighty. I will say of the LORD, He is my refuge and my fortress: my God; in him will I trust.'],
        ['book' => 'John', 'book_name' => 'John', 'chapter' => 14, 'verse' => 27, 'reference' => 'John 14:27', 'text' => 'Peace I leave with you, my peace I give unto you: not as the world giveth, give I unto you. Let not your heart be troubled, neither let it be afraid.'],
        ['book' => 'Lam', 'book_name' => 'Lamentations', 'chapter' => 3, 'verse' => 22, 'verse_end' => 23, 'reference' => 'Lamentations 3:22-23', 'text' => 'It is of the LORD\'s mercies that we are not consumed, because his compassions fail not. They are new every morning: great is thy faithfulness.'],
        ['book' => 'Rom', 'book_name' => 'Romans', 'chapter' => 15, 'verse' => 13, 'reference' => 'Romans 15:13', 'text' => 'Now the God of hope fill you with all joy and peace in believing, that ye may abound in hope, through the power of the Holy Ghost.'],
        ['book' => 'Ps', 'book_name' => 'Psalms', 'chapter' => 34, 'verse' => 8, 'reference' => 'Psalm 34:8', 'text' => 'O taste and see that the LORD is good: blessed is the man that trusteth in him.'],
        ['book' => '2Cor', 'book_name' => '2 Corinthians', 'chapter' => 5, 'verse' => 17, 'reference' => '2 Corinthians 5:17', 'text' => 'Therefore if any man be in Christ, he is a new creature: old things are passed away; behold, all things are become new.'],
        ['book' => 'Ps', 'book_name' => 'Psalms', 'chapter' => 56, 'verse' => 3, 'reference' => 'Psalm 56:3', 'text' => 'What time I am afraid, I will trust in thee.'],
        ['book' => 'Isa', 'book_name' => 'Isaiah', 'chapter' => 26, 'verse' => 3, 'reference' => 'Isaiah 26:3', 'text' => 'Thou wilt keep him in perfect peace, whose mind is stayed on thee: because he trusteth in thee.'],
        ['book' => 'Ps', 'book_name' => 'Psalms', 'chapter' => 1, 'verse' => 1, 'verse_end' => 2, 'reference' => 'Psalm 1:1-2', 'text' => 'Blessed is the man that walketh not in the counsel of the ungodly, nor standeth in the way of sinners, nor sitteth in the seat of the scornful. But his delight is in the law of the LORD; and in his law doth he meditate day and night.'],
        ['book' => 'Matt', 'book_name' => 'Matthew', 'chapter' => 28, 'verse' => 20, 'reference' => 'Matthew 28:20', 'text' => 'Teaching them to observe all things whatsoever I have commanded you: and, lo, I am with you alway, even unto the end of the world. Amen.'],
        ['book' => 'Eph', 'book_name' => 'Ephesians', 'chapter' => 6, 'verse' => 10, 'reference' => 'Ephesians 6:10', 'text' => 'Finally, my brethren, be strong in the Lord, and in the power of his might.'],
        ['book' => 'Ps', 'book_name' => 'Psalms', 'chapter' => 118, 'verse' => 24, 'reference' => 'Psalm 118:24', 'text' => 'This is the day which the LORD hath made; we will rejoice and be glad in it.'],
        ['book' => '1John', 'book_name' => '1 John', 'chapter' => 4, 'verse' => 19, 'reference' => '1 John 4:19', 'text' => 'We love him, because he first loved us.'],
        ['book' => 'Ps', 'book_name' => 'Psalms', 'chapter' => 100, 'verse' => 4, 'reference' => 'Psalm 100:4', 'text' => 'Enter into his gates with thanksgiving, and into his courts with praise: be thankful unto him, and bless his name.'],
        ['book' => 'Rom', 'book_name' => 'Romans', 'chapter' => 5, 'verse' => 8, 'reference' => 'Romans 5:8', 'text' => 'But God commendeth his love toward us, in that, while we were yet sinners, Christ died for us.'],
        ['book' => 'Ps', 'book_name' => 'Psalms', 'chapter' => 16, 'verse' => 11, 'reference' => 'Psalm 16:11', 'text' => 'Thou wilt shew me the path of life: in thy presence is fulness of joy; at thy right hand there are pleasures for evermore.'],
        ['book' => 'John', 'book_name' => 'John', 'chapter' => 10, 'verse' => 10, 'reference' => 'John 10:10', 'text' => 'The thief cometh not, but for to steal, and to kill, and to destroy: I am come that they might have life, and that they might have it more abundantly.'],
        ['book' => 'Mic', 'book_name' => 'Micah', 'chapter' => 6, 'verse' => 8, 'reference' => 'Micah 6:8', 'text' => 'He hath shewed thee, O man, what is good; and what doth the LORD require of thee, but to do justly, and to love mercy, and to walk humbly with thy God?'],
        ['book' => 'Ps', 'book_name' => 'Psalms', 'chapter' => 19, 'verse' => 14, 'reference' => 'Psalm 19:14', 'text' => 'Let the words of my mouth, and the meditation of my heart, be acceptable in thy sight, O LORD, my strength, and my redeemer.'],
        ['book' => 'Jas', 'book_name' => 'James', 'chapter' => 1, 'verse' => 5, 'reference' => 'James 1:5', 'text' => 'If any of you lack wisdom, let him ask of God, that giveth to all men liberally, and upbraideth not; and it shall be given him.'],
        ['book' => 'Ps', 'book_name' => 'Psalms', 'chapter' => 32, 'verse' => 8, 'reference' => 'Psalm 32:8', 'text' => 'I will instruct thee and teach thee in the way which thou shalt go: I will guide thee with mine eye.'],
        ['book' => '2Cor', 'book_name' => '2 Corinthians', 'chapter' => 12, 'verse' => 9, 'reference' => '2 Corinthians 12:9', 'text' => 'And he said unto me, My grace is sufficient for thee: for my strength is made perfect in weakness. Most gladly therefore will I rather glory in my infirmities, that the power of Christ may rest upon me.'],
        ['book' => 'Gen', 'book_name' => 'Genesis', 'chapter' => 1, 'verse' => 1, 'reference' => 'Genesis 1:1', 'text' => 'In the beginning God created the heaven and the earth.'],
        ['book' => 'Ps', 'book_name' => 'Psalms', 'chapter' => 121, 'verse' => 1, 'verse_end' => 2, 'reference' => 'Psalm 121:1-2', 'text' => 'I will lift up mine eyes unto the hills, from whence cometh my help. My help cometh from the LORD, which made heaven and earth.'],
        ['book' => 'John', 'book_name' => 'John', 'chapter' => 8, 'verse' => 32, 'reference' => 'John 8:32', 'text' => 'And ye shall know the truth, and the truth shall make you free.'],
        ['book' => 'Ps', 'book_name' => 'Psalms', 'chapter' => 23, 'verse' => 4, 'reference' => 'Psalm 23:4', 'text' => 'Yea, though I walk through the valley of the shadow of death, I will fear no evil: for thou art with me; thy rod and thy staff they comfort me.'],
        ['book' => '1Thess', 'book_name' => '1 Thessalonians', 'chapter' => 5, 'verse' => 16, 'verse_end' => 18, 'reference' => '1 Thessalonians 5:16-18', 'text' => 'Rejoice evermore. Pray without ceasing. In every thing give thanks: for this is the will of God in Christ Jesus concerning you.'],
        ['book' => 'Ps', 'book_name' => 'Psalms', 'chapter' => 42, 'verse' => 1, 'reference' => 'Psalm 42:1', 'text' => 'As the hart panteth after the water brooks, so panteth my soul after thee, O God.'],
        ['book' => 'Matt', 'book_name' => 'Matthew', 'chapter' => 5, 'verse' => 16, 'reference' => 'Matthew 5:16', 'text' => 'Let your light so shine before men, that they may see your good works, and glorify your Father which is in heaven.'],
        ['book' => 'Col', 'book_name' => 'Colossians', 'chapter' => 3, 'verse' => 2, 'reference' => 'Colossians 3:2', 'text' => 'Set your affection on things above, not on things on the earth.'],
        ['book' => 'Prov', 'book_name' => 'Proverbs', 'chapter' => 18, 'verse' => 10, 'reference' => 'Proverbs 18:10', 'text' => 'The name of the LORD is a strong tower: the righteous runneth into it, and is safe.'],
        ['book' => 'John', 'book_name' => 'John', 'chapter' => 16, 'verse' => 33, 'reference' => 'John 16:33', 'text' => 'These things I have spoken unto you, that in me ye might have peace. In the world ye shall have tribulation: but be of good cheer; I have overcome the world.'],
        ['book' => 'Ps', 'book_name' => 'Psalms', 'chapter' => 103, 'verse' => 1, 'reference' => 'Psalm 103:1', 'text' => 'Bless the LORD, O my soul: and all that is within me, bless his holy name.'],
        ['book' => 'Heb', 'book_name' => 'Hebrews', 'chapter' => 12, 'verse' => 2, 'reference' => 'Hebrews 12:2', 'text' => 'Looking unto Jesus the author and finisher of our faith; who for the joy that was set before him endured the cross, despising the shame, and is set down at the right hand of the throne of God.'],
        ['book' => 'Ps', 'book_name' => 'Psalms', 'chapter' => 55, 'verse' => 22, 'reference' => 'Psalm 55:22', 'text' => 'Cast thy burden upon the LORD, and he shall sustain thee: he shall never suffer the righteous to be moved.'],
        ['book' => 'Phil', 'book_name' => 'Philippians', 'chapter' => 4, 'verse' => 6, 'verse_end' => 7, 'reference' => 'Philippians 4:6-7', 'text' => 'Be careful for nothing; but in every thing by prayer and supplication with thanksgiving let your requests be made known unto God. And the peace of God, which passeth all understanding, shall keep your hearts and minds through Christ Jesus.'],
        ['book' => 'Ps', 'book_name' => 'Psalms', 'chapter' => 150, 'verse' => 6, 'reference' => 'Psalm 150:6', 'text' => 'Let every thing that hath breath praise the LORD. Praise ye the LORD.'],
        ['book' => 'John', 'book_name' => 'John', 'chapter' => 1, 'verse' => 1, 'reference' => 'John 1:1', 'text' => 'In the beginning was the Word, and the Word was with God, and the Word was God.'],
        ['book' => 'Rev', 'book_name' => 'Revelation', 'chapter' => 21, 'verse' => 4, 'reference' => 'Revelation 21:4', 'text' => 'And God shall wipe away all tears from their eyes; and there shall be no more death, neither sorrow, nor crying, neither shall there be any more pain: for the former things are passed away.'],
        ['book' => 'Nah', 'book_name' => 'Nahum', 'chapter' => 1, 'verse' => 7, 'reference' => 'Nahum 1:7', 'text' => 'The LORD is good, a strong hold in the day of trouble; and he knoweth them that trust in him.'],
        ['book' => 'Ps', 'book_name' => 'Psalms', 'chapter' => 4, 'verse' => 8, 'reference' => 'Psalm 4:8', 'text' => 'I will both lay me down in peace, and sleep: for thou, LORD, only makest me dwell in safety.'],
        ['book' => 'Matt', 'book_name' => 'Matthew', 'chapter' => 7, 'verse' => 7, 'reference' => 'Matthew 7:7', 'text' => 'Ask, and it shall be given you; seek, and ye shall find; knock, and it shall be opened unto you.'],
        ['book' => 'Prov', 'book_name' => 'Proverbs', 'chapter' => 22, 'verse' => 6, 'reference' => 'Proverbs 22:6', 'text' => 'Train up a child in the way he should go: and when he is old, he will not depart from it.'],
        ['book' => 'Ps', 'book_name' => 'Psalms', 'chapter' => 107, 'verse' => 1, 'reference' => 'Psalm 107:1', 'text' => 'O give thanks unto the LORD, for he is good: for his mercy endureth for ever.'],
        ['book' => 'Isa', 'book_name' => 'Isaiah', 'chapter' => 43, 'verse' => 2, 'reference' => 'Isaiah 43:2', 'text' => 'When thou passest through the waters, I will be with thee; and through the rivers, they shall not overflow thee: when thou walkest through the fire, thou shalt not be burned; neither shall the flame kindle upon thee.'],
        ['book' => 'Ps', 'book_name' => 'Psalms', 'chapter' => 73, 'verse' => 26, 'reference' => 'Psalm 73:26', 'text' => 'My flesh and my heart faileth: but God is the strength of my heart, and my portion for ever.'],
        ['book' => 'John', 'book_name' => 'John', 'chapter' => 15, 'verse' => 5, 'reference' => 'John 15:5', 'text' => 'I am the vine, ye are the branches: He that abideth in me, and I in him, the same bringeth forth much fruit: for without me ye can do nothing.'],
        ['book' => 'Prov', 'book_name' => 'Proverbs', 'chapter' => 4, 'verse' => 23, 'reference' => 'Proverbs 4:23', 'text' => 'Keep thy heart with all diligence; for out of it are the issues of life.'],
        ['book' => '1Cor', 'book_name' => '1 Corinthians', 'chapter' => 13, 'verse' => 4, 'verse_end' => 7, 'reference' => '1 Corinthians 13:4-7', 'text' => 'Charity suffereth long, and is kind; charity envieth not; charity vaunteth not itself, is not puffed up, Doth not behave itself unseemly, seeketh not her own, is not easily provoked, thinketh no evil; Rejoiceth not in iniquity, but rejoiceth in the truth; Beareth all things, believeth all things, hopeth all things, endureth all things.'],
        ['book' => 'Ps', 'book_name' => 'Psalms', 'chapter' => 62, 'verse' => 1, 'verse_end' => 2, 'reference' => 'Psalm 62:1-2', 'text' => 'Truly my soul waiteth upon God: from him cometh my salvation. He only is my rock and my salvation; he is my defence; I shall not be greatly moved.'],
        ['book' => 'Matt', 'book_name' => 'Matthew', 'chapter' => 19, 'verse' => 26, 'reference' => 'Matthew 19:26', 'text' => 'But Jesus beheld them, and said unto them, With men this is impossible; but with God all things are possible.'],
        ['book' => 'Eph', 'book_name' => 'Ephesians', 'chapter' => 3, 'verse' => 20, 'reference' => 'Ephesians 3:20', 'text' => 'Now unto him that is able to do exceeding abundantly above all that we ask or think, according to the power that worketh in us.'],
        ['book' => 'Ps', 'book_name' => 'Psalms', 'chapter' => 84, 'verse' => 11, 'reference' => 'Psalm 84:11', 'text' => 'For the LORD God is a sun and shield: the LORD will give grace and glory: no good thing will he withhold from them that walk uprightly.'],
        ['book' => 'Rom', 'book_name' => 'Romans', 'chapter' => 8, 'verse' => 38, 'verse_end' => 39, 'reference' => 'Romans 8:38-39', 'text' => 'For I am persuaded, that neither death, nor life, nor angels, nor principalities, nor powers, nor things present, nor things to come, Nor height, nor depth, nor any other creature, shall be able to separate us from the love of God, which is in Christ Jesus our Lord.'],
        ['book' => 'Ps', 'book_name' => 'Psalms', 'chapter' => 145, 'verse' => 18, 'reference' => 'Psalm 145:18', 'text' => 'The LORD is nigh unto all them that call upon him, to all that call upon him in truth.'],
        ['book' => 'Isa', 'book_name' => 'Isaiah', 'chapter' => 55, 'verse' => 8, 'verse_end' => 9, 'reference' => 'Isaiah 55:8-9', 'text' => 'For my thoughts are not your thoughts, neither are your ways my ways, saith the LORD. For as the heavens are higher than the earth, so are my ways higher than your ways, and my thoughts than your thoughts.'],
        ['book' => 'John', 'book_name' => 'John', 'chapter' => 11, 'verse' => 25, 'verse_end' => 26, 'reference' => 'John 11:25-26', 'text' => 'Jesus said unto her, I am the resurrection, and the life: he that believeth in me, though he were dead, yet shall he live: And whosoever liveth and believeth in me shall never die.'],
        ['book' => 'Ps', 'book_name' => 'Psalms', 'chapter' => 40, 'verse' => 1, 'verse_end' => 2, 'reference' => 'Psalm 40:1-2', 'text' => 'I waited patiently for the LORD; and he inclined unto me, and heard my cry. He brought me up also out of an horrible pit, out of the miry clay, and set my feet upon a rock, and established my goings.'],
        ['book' => 'Prov', 'book_name' => 'Proverbs', 'chapter' => 16, 'verse' => 3, 'reference' => 'Proverbs 16:3', 'text' => 'Commit thy works unto the LORD, and thy thoughts shall be established.'],
        ['book' => 'Jas', 'book_name' => 'James', 'chapter' => 4, 'verse' => 8, 'reference' => 'James 4:8', 'text' => 'Draw nigh to God, and he will draw nigh to you. Cleanse your hands, ye sinners; and purify your hearts, ye double minded.'],
        ['book' => '1John', 'book_name' => '1 John', 'chapter' => 1, 'verse' => 9, 'reference' => '1 John 1:9', 'text' => 'If we confess our sins, he is faithful and just to forgive us our sins, and to cleanse us from all unrighteousness.'],
        ['book' => 'Ps', 'book_name' => 'Psalms', 'chapter' => 51, 'verse' => 10, 'reference' => 'Psalm 51:10', 'text' => 'Create in me a clean heart, O God; and renew a right spirit within me.'],
        ['book' => 'Matt', 'book_name' => 'Matthew', 'chapter' => 22, 'verse' => 37, 'verse_end' => 39, 'reference' => 'Matthew 22:37-39', 'text' => 'Jesus said unto him, Thou shalt love the Lord thy God with all thy heart, and with all thy soul, and with all thy mind. This is the first and great commandment. And the second is like unto it, Thou shalt love thy neighbour as thyself.'],
        ['book' => 'Ps', 'book_name' => 'Psalms', 'chapter' => 46, 'verse' => 10, 'reference' => 'Psalm 46:10', 'text' => 'Be still, and know that I am God: I will be exalted among the heathen, I will be exalted in the earth.'],
        ['book' => 'Heb', 'book_name' => 'Hebrews', 'chapter' => 13, 'verse' => 5, 'reference' => 'Hebrews 13:5', 'text' => 'Let your conversation be without covetousness; and be content with such things as ye have: for he hath said, I will never leave thee, nor forsake thee.'],
        ['book' => 'Ps', 'book_name' => 'Psalms', 'chapter' => 28, 'verse' => 7, 'reference' => 'Psalm 28:7', 'text' => 'The LORD is my strength and my shield; my heart trusted in him, and I am helped: therefore my heart greatly rejoiceth; and with my song will I praise him.'],
        ['book' => 'Isa', 'book_name' => 'Isaiah', 'chapter' => 40, 'verse' => 29, 'reference' => 'Isaiah 40:29', 'text' => 'He giveth power to the faint; and to them that have no might he increaseth strength.'],
        ['book' => 'Ps', 'book_name' => 'Psalms', 'chapter' => 86, 'verse' => 5, 'reference' => 'Psalm 86:5', 'text' => 'For thou, Lord, art good, and ready to forgive; and plenteous in mercy unto all them that call upon thee.'],
        ['book' => 'Phil', 'book_name' => 'Philippians', 'chapter' => 1, 'verse' => 6, 'reference' => 'Philippians 1:6', 'text' => 'Being confident of this very thing, that he which hath begun a good work in you will perform it until the day of Jesus Christ.'],
        ['book' => 'Ps', 'book_name' => 'Psalms', 'chapter' => 9, 'verse' => 10, 'reference' => 'Psalm 9:10', 'text' => 'And they that know thy name will put their trust in thee: for thou, LORD, hast not forsaken them that seek thee.'],
        ['book' => 'John', 'book_name' => 'John', 'chapter' => 14, 'verse' => 6, 'reference' => 'John 14:6', 'text' => 'Jesus saith unto him, I am the way, the truth, and the life: no man cometh unto the Father, but by me.'],
        ['book' => 'Ps', 'book_name' => 'Psalms', 'chapter' => 138, 'verse' => 8, 'reference' => 'Psalm 138:8', 'text' => 'The LORD will perfect that which concerneth me: thy mercy, O LORD, endureth for ever: forsake not the works of thine own hands.'],
        ['book' => 'Hab', 'book_name' => 'Habakkuk', 'chapter' => 3, 'verse' => 17, 'verse_end' => 18, 'reference' => 'Habakkuk 3:17-18', 'text' => 'Although the fig tree shall not blossom, neither shall fruit be in the vines; the labour of the olive shall fail, and the fields shall yield no meat; the flock shall be cut off from the fold, and there shall be no herd in the stalls: Yet I will rejoice in the LORD, I will joy in the God of my salvation.'],
    ];
}

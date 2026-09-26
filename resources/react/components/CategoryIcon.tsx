import {
    Apple, Baby, Battery, BatteryCharging, Beef, Bike, BookOpen, Cable, Cake, Camera, Candy, Car, Cigarette, Coffee,
    CookingPot, Cpu, Croissant, CupSoda, Drill, Droplets, Dumbbell, Egg, Fan, Fish, Flame, Flower2, Gamepad2, Gem,
    Gift, Glasses, Hammer, HardDrive, Headphones, IceCreamCone, Keyboard, Laptop, LayoutGrid, Lightbulb, MemoryStick,
    Milk, Monitor, Mouse, Package, PawPrint, Pencil, Pill, Plug, Printer, Puzzle, Router, Sandwich, Shield, ShieldCheck,
    Shirt, ShoppingBasket, Signal, Smartphone, Snowflake, Sofa, Sparkles, Speaker, SprayCan, Tablet, Tag, Tv, Watch,
    Wheat, Wrench, type LucideProps,
} from 'lucide-react';

/**
 * Categories have no icon column: pick one from the name. Order matters —
 * the first matching keyword wins, so "Téléphones reconditionnés" is a phone
 * and not a wrench, "Consoles & jeux vidéo" a gamepad and not a TV, and
 * "Accessoires auto" a car and not a box. Unknown names get a plain tag.
 * CategorySeeder's names each land on their own icon here.
 */
const RULES: [RegExp, typeof Tag][] = [
    // Phones, computing, electronics
    [/t[ée]l[ée]phone|smartphone|mobile|هاتف|تليفون|بورطابل/i, Smartphone],
    [/tablette|ipad|لوحي|لوحية/i, Tablet],
    [/ordinateur|laptop|pc portable|حاسوب|لابتوب/i, Laptop],
    [/montre|smartwatch|ساعة|ساعات/i, Watch],
    [/composant|processeur|carte m[èe]re|مكونات/i, Cpu],
    [/console|jeux? vid[ée]o|gaming|manette|ألعاب فيديو|بلايستيشن/i, Gamepad2],
    [/\btv\b|t[ée]l[ée]vis|تلفاز|تلفزيون/i, Tv],
    [/enceinte|haut-?parleur|baffle|مكبر/i, Speaker],
    [/[ée]couteur|audio|casque|سماع|صوت/i, Headphones],
    [/c[âa]ble|كابل|كابلات/i, Cable],
    [/chargeur|power ?bank|شاحن|شارجور/i, BatteryCharging],
    [/[ée]cran|afficheur|شاشة|شاشات|إيكران/i, Monitor],
    [/batterie|بطارية|بطاريات/i, Battery],
    [/m[ée]moire|cl[ée]s? usb|flash|ذاكرة|فلاش/i, MemoryStick],
    [/disque|ssd|stockage|قرص/i, HardDrive],
    [/souris|فأرة/i, Mouse],
    [/clavier|لوحة المفاتيح/i, Keyboard],
    [/imprimant|encre|toner|طابعة|حبر/i, Printer],
    [/r[ée]seau|wi-?fi|routeur|modem|شبكة|راوتر/i, Router],
    [/appareils? photo|cam[ée]ra|كاميرا/i, Camera],
    [/\bsim\b|recharge|forfait|تعبئة|شريحة/i, Signal],
    [/ampoule|[ée]lectricit|[ée]clairage|lampe|إنارة|كهرباء/i, Lightbulb],
    [/rallonge|prise|multiprise|مقبس/i, Plug],
    [/ventilat|climat|مروحة|مكيف/i, Fan],
    [/pi[èe]ce|d[ée]tach|قطع/i, Wrench],
    [/coque|[ée]tui|غطاء|كوك/i, Shield],
    [/verre|film|protection|حماية|زجاج/i, ShieldCheck],
    [/r[ée]paration|service|إصلاح|تصليح|خدمات/i, Hammer],
    [/outil|bricolage|أدوات/i, Drill],
    [/\bauto\b|voiture|سيارة|طوموبيل/i, Car],

    // Food
    [/boisson|soda|\bjus\b|\beaux?\b|مشروب|عصير/i, CupSoda],
    [/caf[ée]|قهوة|أتاي|شاي/i, Coffee],
    [/lait|fromage|yaourt|cr[èe]merie|حليب|ألبان|جبن/i, Milk],
    [/boulanger|\bpain|viennoiser|خبز|مخبزة/i, Croissant],
    [/p[âa]tisser|g[âa]teau|حلويات|كيك/i, Cake],
    [/fruit|l[ée]gume|فواكه|خضر/i, Apple],
    [/boucher|viande|volaille|لحوم|لحم|جزارة/i, Beef],
    [/poisson|fruits de mer|سمك|حوت/i, Fish],
    [/œuf|oeuf|بيض/i, Egg],
    [/c[ée]r[ée]ale|farine|semoule|حبوب|دقيق|طحين/i, Wheat],
    [/confiser|bonbon|biscuit|chocolat|شوكولاتة|بسكويت/i, Candy],
    [/glace|ice cream|مثلجات|آيس كريم/i, IceCreamCone],
    [/sandwich|snack|fast.?food|سندويتش/i, Sandwich],
    [/surgel|congel|مجمد/i, Snowflake],
    [/[ée]picerie|alimentation|بقالة|مواد غذائية/i, ShoppingBasket],

    // Household, personal, leisure
    [/hygi[èe]ne|نظافة/i, Droplets],
    [/beaut|cosm[ée]t|parfum|maquillage|تجميل|عطور/i, Sparkles],
    [/pharma|m[ée]dic|sant[ée]|صيدلية|دواء|صحة/i, Pill],
    [/entretien|m[ée]nage|nettoy|d[ée]tergent|تنظيف|منظفات/i, SprayCan],
    [/b[ée]b[ée]|pu[ée]ricult|رضع|أطفال/i, Baby],
    [/v[êe]tement|textile|habit|ملابس|حوايج/i, Shirt],
    [/papeterie|fourniture|scolaire|bureau|قرطاسية|مكتبة/i, Pencil],
    [/livre|magazine|journa|كتب|مجلات/i, BookOpen],
    [/jouet|لعب|ألعاب/i, Puzzle],
    [/cadeau|هدايا|هدية/i, Gift],
    [/sport|fitness|musculation|رياضة/i, Dumbbell],
    [/animal|animaux|chien|\bchats?\b|حيوانات/i, PawPrint],
    [/jardin|plante|fleur|حديقة|نباتات|ورود/i, Flower2],
    [/maison|d[ée]co|meuble|منزل|ديكور|أثاث/i, Sofa],
    [/cuisine|ustensile|vaisselle|مطبخ|أواني/i, CookingPot],
    [/tabac|cigarette|تبغ|دخان|طابا/i, Cigarette],
    [/\bgaz|charbon|butane|غاز|فحم|بوطا/i, Flame],
    [/bijou|joaill|مجوهرات|ذهب/i, Gem],
    [/lunette|optique|نظارات/i, Glasses],
    [/v[ée]lo|trottinette|دراجة/i, Bike],

    // Catch-all for the rest of the add-ons.
    [/accessoire|إكسسوار|أكسسوار/i, Package],
];

export function categoryIcon(name: string | null | undefined): typeof Tag {
    if (!name) return LayoutGrid;
    return RULES.find(([re]) => re.test(name))?.[1] ?? Tag;
}

/** The icon for a category name; null/undefined means "all categories". */
// SVG attributes carry their own `name`; ours is the category's, so drop theirs.
export function CategoryIcon({ name, ...props }: { name: string | null | undefined } & Omit<LucideProps, 'name'>) {
    const Icon = categoryIcon(name);
    return <Icon {...props} />;
}

import {
    Battery, BatteryCharging, Cable, Droplets, Hammer, Headphones, LayoutGrid, Monitor, Package,
    Shield, ShoppingBasket, Smartphone, Snowflake, Tag, Wrench, type LucideProps,
} from 'lucide-react';

/**
 * Categories have no icon column: pick one from the name. Order matters —
 * the first matching keyword wins, so "Téléphones reconditionnés" is a phone
 * and not a wrench. Unknown names get a plain tag.
 */
const RULES: [RegExp, typeof Tag][] = [
    [/t[ée]l[ée]phone|smartphone|mobile|هاتف|تليفون|بورطابل/i, Smartphone],
    [/c[âa]ble|كابل|كابلات/i, Cable],
    [/chargeur|power ?bank|شاحن|شارجور/i, BatteryCharging],
    [/[ée]couteur|audio|casque|enceinte|سماع|صوت/i, Headphones],
    [/[ée]cran|شاشة|شاشات|إيكران/i, Monitor],
    [/batterie|بطارية|بطاريات/i, Battery],
    [/pi[èe]ce|d[ée]tach|قطع/i, Wrench],
    [/coque|protection|verre|غطاء|حماية/i, Shield],
    [/r[ée]paration|service|إصلاح|تصليح|خدمات/i, Hammer],
    [/[ée]picerie|alimentation|boisson|بقالة|مشروب/i, ShoppingBasket],
    [/hygi[èe]ne|نظافة/i, Droplets],
    [/surgel|congel|مجمد/i, Snowflake],
    [/accessoire|إكسسوار|أكسسوار/i, Package],
];

export function categoryIcon(name: string | null | undefined): typeof Tag {
    if (!name) return LayoutGrid;
    return RULES.find(([re]) => re.test(name))?.[1] ?? Tag;
}

/** The icon for a category name; null/undefined means "all categories". */
export function CategoryIcon({ name, ...props }: { name: string | null | undefined } & LucideProps) {
    const Icon = categoryIcon(name);
    return <Icon {...props} />;
}

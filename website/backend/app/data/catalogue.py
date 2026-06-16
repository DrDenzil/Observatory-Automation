"""
Static in-memory catalogue of common astronomical objects.
Coordinates are J2000 in decimal degrees.
Each entry: (canonical_name, ra_deg, dec_deg, object_type, [aliases])
"""

import re

# fmt: off
CATALOGUE: list[tuple[str, float, float, str, list[str]]] = [
    # ---- Messier objects ----
    ("M1",  83.8221,  22.0146, "SNR",      ["Crab Nebula", "NGC 1952"]),
    ("M2",  323.3625, -0.8233, "GC",       ["NGC 7089"]),
    ("M3",  205.5483, 28.3775, "GC",       ["NGC 5272"]),
    ("M4",  245.8967,-26.5256, "GC",       ["NGC 6121"]),
    ("M5",  229.6392,  2.0808, "GC",       ["NGC 5904"]),
    ("M6",  265.0792,-32.2133, "OC",       ["Butterfly Cluster", "NGC 6405"]),
    ("M7",  268.4583,-34.8417, "OC",       ["Ptolemy Cluster", "NGC 6475"]),
    ("M8",  271.0317,-24.3878, "EN",       ["Lagoon Nebula", "NGC 6523"]),
    ("M9",  259.7996,-18.5158, "GC",       ["NGC 6333"]),
    ("M10", 254.2879, -4.0997, "GC",       ["NGC 6254"]),
    ("M11", 282.7658, -6.2722, "OC",       ["Wild Duck Cluster", "NGC 6705"]),
    ("M12", 251.8117, -1.9486, "GC",       ["NGC 6218"]),
    ("M13", 250.4233, 36.4611, "GC",       ["Hercules Cluster", "NGC 6205"]),
    ("M14", 269.6792, -3.2456, "GC",       ["NGC 6402"]),
    ("M15", 322.4929, 12.1670, "GC",       ["NGC 7078"]),
    ("M16", 274.7000,-13.8000, "EN+OC",    ["Eagle Nebula", "NGC 6611"]),
    ("M17", 275.2000,-16.1833, "EN",       ["Omega Nebula", "Swan Nebula", "NGC 6618"]),
    ("M18", 274.0000,-17.1000, "OC",       ["NGC 6613"]),
    ("M19", 255.6592,-26.2681, "GC",       ["NGC 6273"]),
    ("M20", 270.5167,-23.0333, "EN",       ["Trifid Nebula", "NGC 6514"]),
    ("M21", 271.9167,-22.5000, "OC",       ["NGC 6531"]),
    ("M22", 279.0996,-23.9047, "GC",       ["NGC 6656"]),
    ("M23", 269.2667,-18.9833, "OC",       ["NGC 6494"]),
    ("M24", 274.3167,-18.5167, "SC",       ["Sagittarius Star Cloud"]),
    ("M25", 277.2583,-19.1167, "OC",       ["IC 4725"]),
    ("M26", 281.3500, -9.3833, "OC",       ["NGC 6694"]),
    ("M27", 299.9017, 22.7214, "PN",       ["Dumbbell Nebula", "NGC 6853"]),
    ("M28", 276.1333,-24.8700, "GC",       ["NGC 6626"]),
    ("M29", 308.5667, 38.5333, "OC",       ["NGC 6913"]),
    ("M30", 325.0925,-23.1800, "GC",       ["NGC 7099"]),
    ("M31", 10.6847,  41.2692, "Galaxy",   ["Andromeda Galaxy", "NGC 224"]),
    ("M32", 10.6742,  40.8653, "Galaxy",   ["NGC 221"]),
    ("M33", 23.4621,  30.6600, "Galaxy",   ["Triangulum Galaxy", "NGC 598"]),
    ("M34", 40.5250,  42.7833, "OC",       ["NGC 1039"]),
    ("M35", 92.2583,  24.3333, "OC",       ["NGC 2168"]),
    ("M36", 84.0667,  34.1333, "OC",       ["NGC 1960"]),
    ("M37", 88.0583,  32.5500, "OC",       ["NGC 2099"]),
    ("M38", 82.2500,  35.8333, "OC",       ["NGC 1912"]),
    ("M39", 323.3667, 48.4333, "OC",       ["NGC 7092"]),
    ("M40", 185.5667, 58.0833, "DS",       ["Winnecke 4"]),
    ("M41", 101.5000,-20.7667, "OC",       ["NGC 2287"]),
    ("M42", 83.8221,  -5.3911, "EN",       ["Orion Nebula", "NGC 1976"]),
    ("M43", 83.8833,  -5.2667, "EN",       ["De Mairan's Nebula", "NGC 1982"]),
    ("M44", 130.1000, 19.6667, "OC",       ["Beehive Cluster", "Praesepe", "NGC 2632"]),
    ("M45", 56.7500,  24.1167, "OC",       ["Pleiades", "Seven Sisters"]),
    ("M46", 115.4500,-14.8167, "OC",       ["NGC 2437"]),
    ("M47", 114.1167,-14.4833, "OC",       ["NGC 2422"]),
    ("M48", 123.4167, -5.7333, "OC",       ["NGC 2548"]),
    ("M49", 187.4458,  8.0003, "Galaxy",   ["NGC 4472"]),
    ("M50", 105.7167, -8.3667, "OC",       ["NGC 2323"]),
    ("M51", 202.4696, 47.1952, "Galaxy",   ["Whirlpool Galaxy", "NGC 5194"]),
    ("M52", 351.5333, 61.5833, "OC",       ["NGC 7654"]),
    ("M53", 198.2292, 18.1681, "GC",       ["NGC 5024"]),
    ("M54", 283.7633,-30.4783, "GC",       ["NGC 6715"]),
    ("M55", 294.9983,-30.9658, "GC",       ["NGC 6809"]),
    ("M56", 289.1500, 30.1833, "GC",       ["NGC 6779"]),
    ("M57", 283.3962, 33.0292, "PN",       ["Ring Nebula", "NGC 6720"]),
    ("M58", 189.4292, 11.8181, "Galaxy",   ["NGC 4579"]),
    ("M59", 190.5083, 11.6472, "Galaxy",   ["NGC 4621"]),
    ("M60", 190.9167, 11.5528, "Galaxy",   ["NGC 4649"]),
    ("M61", 185.4792,  4.4736, "Galaxy",   ["NGC 4303"]),
    ("M62", 255.3033,-30.1131, "GC",       ["NGC 6266"]),
    ("M63", 198.9558, 42.0297, "Galaxy",   ["Sunflower Galaxy", "NGC 5055"]),
    ("M64", 194.1817, 21.6825, "Galaxy",   ["Black Eye Galaxy", "NGC 4826"]),
    ("M65", 169.7333, 13.0922, "Galaxy",   ["NGC 3623"]),
    ("M66", 170.0625, 12.9917, "Galaxy",   ["NGC 3627"]),
    ("M67", 132.8250, 11.8167, "OC",       ["NGC 2682"]),
    ("M68", 189.8667,-26.7442, "GC",       ["NGC 4590"]),
    ("M69", 277.8458,-32.3481, "GC",       ["NGC 6637"]),
    ("M70", 281.0208,-32.2919, "GC",       ["NGC 6681"]),
    ("M71", 298.4333, 18.7833, "GC",       ["NGC 6838"]),
    ("M72", 313.3583,-12.5372, "GC",       ["NGC 6981"]),
    ("M73", 314.7500,-12.6333, "OC",       ["NGC 6994"]),
    ("M74", 24.1742,  15.7833, "Galaxy",   ["Phantom Galaxy", "NGC 628"]),
    ("M75", 301.5208,-21.9217, "GC",       ["NGC 6864"]),
    ("M76", 25.5833,  51.5750, "PN",       ["Little Dumbbell", "NGC 650"]),
    ("M77", 40.6700,  -0.0133, "Galaxy",   ["Cetus A", "NGC 1068"]),
    ("M78", 86.6833,   0.0783, "EN",       ["NGC 2068"]),
    ("M79", 81.0458, -24.5236, "GC",       ["NGC 1904"]),
    ("M80", 244.2600,-22.9756, "GC",       ["NGC 6093"]),
    ("M81", 148.8883, 69.0653, "Galaxy",   ["Bode's Galaxy", "NGC 3031"]),
    ("M82", 148.9683, 69.6797, "Galaxy",   ["Cigar Galaxy", "NGC 3034"]),
    ("M83", 204.2538,-29.8658, "Galaxy",   ["Southern Pinwheel", "NGC 5236"]),
    ("M84", 186.2658, 12.8869, "Galaxy",   ["NGC 4374"]),
    ("M85", 186.3458, 18.1914, "Galaxy",   ["NGC 4382"]),
    ("M86", 186.5458, 12.9461, "Galaxy",   ["NGC 4406"]),
    ("M87", 187.7058, 12.3911, "Galaxy",   ["Virgo A", "NGC 4486"]),
    ("M88", 187.9958, 14.4197, "Galaxy",   ["NGC 4501"]),
    ("M89", 188.9167, 12.5564, "Galaxy",   ["NGC 4552"]),
    ("M90", 188.7333, 13.1628, "Galaxy",   ["NGC 4569"]),
    ("M91", 188.8583, 14.4961, "Galaxy",   ["NGC 4548"]),
    ("M92", 259.2808, 43.1358, "GC",       ["NGC 6341"]),
    ("M93", 116.1583,-23.8500, "OC",       ["NGC 2447"]),
    ("M94", 192.7208, 41.1197, "Galaxy",   ["Croc's Eye Galaxy", "NGC 4736"]),
    ("M95", 160.0000, 11.7036, "Galaxy",   ["NGC 3351"]),
    ("M96", 160.9908, 11.8197, "Galaxy",   ["NGC 3368"]),
    ("M97", 168.6992, 55.0192, "PN",       ["Owl Nebula", "NGC 3587"]),
    ("M98", 183.4583, 14.8997, "Galaxy",   ["NGC 4192"]),
    ("M99", 184.7083, 14.4167, "Galaxy",   ["NGC 4254"]),
    ("M100",185.7292, 15.8225, "Galaxy",   ["NGC 4321"]),
    ("M101",210.8025, 54.3492, "Galaxy",   ["Pinwheel Galaxy", "NGC 5457"]),
    ("M102",226.6233, 55.7633, "Galaxy",   ["Spindle Galaxy", "NGC 5866"]),
    ("M103", 23.5000, 60.6500, "OC",       ["NGC 581"]),
    ("M104",189.9979,-11.6231, "Galaxy",   ["Sombrero Galaxy", "NGC 4594"]),
    ("M105",161.9550, 12.5817, "Galaxy",   ["NGC 3379"]),
    ("M106",184.7400, 47.3036, "Galaxy",   ["NGC 4258"]),
    ("M107",248.1325,-13.0533, "GC",       ["NGC 6171"]),
    ("M108",167.8792, 55.6739, "Galaxy",   ["Surfboard Galaxy", "NGC 3556"]),
    ("M109",179.3992, 53.3744, "Galaxy",   ["NGC 3992"]),
    ("M110", 10.0917, 41.6853, "Galaxy",   ["NGC 205"]),

    # ---- Bright named stars ----
    ("Sirius",    101.2872,-16.7161, "Star", ["Alpha CMa", "α CMa", "HD 48915"]),
    ("Canopus",    95.9879,-52.6957, "Star", ["Alpha Car", "α Car"]),
    ("Arcturus",  213.9153, 19.1822, "Star", ["Alpha Boo", "α Boo"]),
    ("Vega",      279.2347, 38.7836, "Star", ["Alpha Lyr", "α Lyr"]),
    ("Capella",    79.1722, 45.9980, "Star", ["Alpha Aur", "α Aur"]),
    ("Rigel",      78.6345, -8.2016, "Star", ["Beta Ori", "β Ori"]),
    ("Procyon",   114.8255,  5.2250, "Star", ["Alpha CMi", "α CMi"]),
    ("Betelgeuse", 88.7929,  7.4071, "Star", ["Alpha Ori", "α Ori"]),
    ("Achernar",   24.4288,-57.2367, "Star", ["Alpha Eri", "α Eri"]),
    ("Hadar",     210.9558,-60.3731, "Star", ["Beta Cen", "β Cen"]),
    ("Altair",    297.6958,  8.8683, "Star", ["Alpha Aql", "α Aql"]),
    ("Aldebaran",  68.9800, 16.5093, "Star", ["Alpha Tau", "α Tau"]),
    ("Spica",     201.2983,-11.1613, "Star", ["Alpha Vir", "α Vir"]),
    ("Antares",   247.3519,-26.4321, "Star", ["Alpha Sco", "α Sco"]),
    ("Pollux",    116.3289, 28.0262, "Star", ["Beta Gem", "β Gem"]),
    ("Fomalhaut", 344.4127,-29.6223, "Star", ["Alpha PsA", "α PsA"]),
    ("Deneb",     310.3580, 45.2803, "Star", ["Alpha Cyg", "α Cyg"]),
    ("Regulus",   152.0929, 11.9672, "Star", ["Alpha Leo", "α Leo"]),
    ("Castor",    113.6497, 31.8883, "Star", ["Alpha Gem", "α Gem"]),
    ("Bellatrix",  81.2829,  6.3497, "Star", ["Gamma Ori", "γ Ori"]),
    ("Polaris",     37.9542, 89.2641, "Star", ["North Star", "Alpha UMi", "α UMi"]),
    ("Mimosa",    191.9303,-59.6888, "Star", ["Beta Cru", "β Cru"]),
    ("Acrux",     186.6496,-63.0990, "Star", ["Alpha Cru", "α Cru"]),
    ("Dubhe",     165.9319, 61.7511, "Star", ["Alpha UMa", "α UMa"]),
    ("Mirfak",     51.0806, 49.8612, "Star", ["Alpha Per", "α Per"]),
    ("Adhara",    104.6564,-28.9721, "Star", ["Epsilon CMa", "ε CMa"]),
    ("Wezen",     107.0979,-26.3931, "Star", ["Delta CMa", "δ CMa"]),
    ("Kaus Australis", 276.0430,-34.3846, "Star", ["Epsilon Sgr", "ε Sgr"]),
    ("Atria",     247.3517,-68.6792, "Star", ["Alpha TrA", "α TrA"]),
    ("Theta Orionis", 83.8186, -5.3894, "Star", ["Trapezium", "Theta1 Ori C"]),

    # ---- Popular NGC / other ----
    ("NGC 869",   34.7500, 57.1333, "OC",  ["h Persei", "Double Cluster"]),
    ("NGC 884",   35.0167, 57.1333, "OC",  ["chi Persei", "Double Cluster"]),
    ("NGC 1499",  60.9000, 36.4000, "EN",  ["California Nebula"]),
    ("NGC 2024",  85.4225, -1.9000, "EN",  ["Flame Nebula"]),
    ("NGC 2070",  84.6758,-69.1014, "EN",  ["Tarantula Nebula", "30 Doradus"]),
    ("NGC 2244",  97.1000,  4.9500, "OC",  ["Rosette Cluster"]),
    ("NGC 2237",  97.2000,  4.9667, "EN",  ["Rosette Nebula"]),
    ("NGC 3372", 161.2667,-59.8667, "EN",  ["Carina Nebula", "Eta Carinae Nebula"]),
    ("NGC 3628", 170.0708, 13.5886, "Galaxy", ["Leo Triplet", "Hamburger Galaxy"]),
    ("NGC 4038", 180.4708,-18.8658, "Galaxy", ["Antennae Galaxies", "NGC 4038/4039"]),
    ("NGC 4565", 189.0875, 25.9878, "Galaxy", ["Needle Galaxy"]),
    ("NGC 5128", 201.3650,-43.0192, "Galaxy", ["Centaurus A", "Cen A"]),
    ("NGC 6334", 260.2167,-35.9000, "EN",  ["Cat's Paw Nebula"]),
    ("NGC 6357", 261.6167,-34.2000, "EN",  ["Lobster Nebula"]),
    ("NGC 6960", 312.0000, 30.7167, "SNR", ["Western Veil Nebula", "Witch's Broom"]),
    ("NGC 6992", 313.4000, 31.7167, "SNR", ["Eastern Veil Nebula", "Veil Nebula"]),
    ("NGC 7000", 314.5000, 44.5000, "EN",  ["North America Nebula"]),
    ("NGC 7009", 317.8917,-11.3619, "PN",  ["Saturn Nebula"]),
    ("NGC 7293", 337.4108,-20.8372, "PN",  ["Helix Nebula"]),
    ("NGC 7331", 339.2667, 34.4158, "Galaxy", []),
    ("NGC 7380", 341.0333, 58.1333, "OC",  ["Wizard Nebula"]),
    ("NGC 7789", 359.3417, 56.7333, "OC",  ["Caroline's Rose"]),
    ("IC 434",   83.7333, -2.5833, "EN",   ["Horsehead Nebula"]),
    ("IC 1805",  38.1667, 61.4500, "OC",   ["Heart Nebula"]),
    ("IC 1848",  43.2000, 60.4333, "OC",   ["Soul Nebula"]),
    ("IC 2118",  76.5000, -7.2167, "EN",   ["Witch Head Nebula"]),
    ("IC 4628",  247.0833,-40.3333,"EN",   ["Prawn Nebula"]),
    ("IC 5146",  328.3667, 47.2667, "OC",  ["Cocoon Nebula"]),
]
# fmt: on


_CATALOGUE_PREFIX = re.compile(
    r'^(NGC|IC|HD|HR|HIP|SAO|Alpha|Beta|Gamma|Delta|Epsilon|Theta|[αβγδεζηθικλμνξοπρστυφχψω]\s)',
    re.IGNORECASE,
)


def _common_name(aliases: list[str]) -> str | None:
    """Return first alias that looks like a plain English name, not a catalogue ID."""
    for a in aliases:
        if not _CATALOGUE_PREFIX.match(a):
            return a
    return None


def _normalise(s: str) -> str:
    """Lowercase, collapse spaces, strip leading zeros from catalogue numbers."""
    s = s.lower().strip()
    # Normalise catalogue prefixes: "m 42" → "m42", "ngc 224" → "ngc224"
    s = re.sub(r'\b(m|ngc|ic)\s*0*(\d+)\b', lambda m: m.group(1) + m.group(2), s)
    return s


def search_local(query: str, limit: int = 5) -> list[dict]:
    q = _normalise(query)
    if len(q) < 2:
        return []

    results = []
    seen: set[str] = set()

    def _score(name: str, aliases: list[str]) -> int | None:
        """Return sort key (lower = better), or None if no match."""
        norm_name = _normalise(name)
        norm_aliases = [_normalise(a) for a in aliases]
        all_names = [norm_name] + norm_aliases
        # Exact match
        if q in all_names:
            return 0
        # Prefix of primary name
        if norm_name.startswith(q):
            return 1
        # Prefix of any alias
        if any(a.startswith(q) for a in norm_aliases):
            return 2
        # Substring of any name
        if any(q in n for n in all_names):
            return 3
        return None

    scored = []
    for name, ra, dec, obj_type, aliases in CATALOGUE:
        score = _score(name, aliases)
        if score is not None and name not in seen:
            seen.add(name)
            scored.append((score, name, ra, dec, obj_type, aliases))

    scored.sort(key=lambda x: (x[0], x[1]))
    for score, name, ra, dec, obj_type, aliases in scored[:limit]:
        results.append({
            "name": name,
            "common_name": _common_name(aliases),
            "ra_deg": ra,
            "dec_deg": dec,
            "type": obj_type,
            "source": "local",
        })
    return results

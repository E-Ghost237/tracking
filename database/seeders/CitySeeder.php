<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Cities for the local geocoder used by the prototype (FR-43). Production uses a geocoding API.
 */
class CitySeeder extends Seeder
{
    /**
     * name, country, lat, lon, population (thousands, approximate).
     *
     * @var array<int, array{0: string, 1: string, 2: float, 3: float, 4: int}>
     */
    private const CITIES = [
        // Central Africa
        ['Bafoussam', 'CM', 5.4778, 10.4176, 400],
        ['Garoua', 'CM', 9.3017, 13.3921, 600], ['Bamenda', 'CM', 5.9631, 10.1591, 500], ['Limbé', 'CM', 4.0167, 9.2, 120],
        ['Kribi', 'CM', 2.9404, 9.9101, 70], ['Maroua', 'CM', 10.591, 14.3159, 400], ['Ngaoundéré', 'CM', 7.3277, 13.5847, 300],
        ['Libreville', 'GA', 0.4162, 9.4673, 850], ['Port-Gentil', 'GA', -0.7193, 8.7815, 140], ['Brazzaville', 'CG', -4.2634, 15.2429, 2400],
        ['Pointe-Noire', 'CG', -4.7692, 11.8664, 1200], ['Kinshasa', 'CD', -4.4419, 15.2663, 17000], ['Lubumbashi', 'CD', -11.6876, 27.5026, 2500],
        ['Bangui', 'CF', 4.3947, 18.5582, 900], ['N\\'Djamena', 'TD', 12.1348, 15.0557, 1500], ['Malabo', 'GQ', 3.7504, 8.7371, 300],
        ['Luanda', 'AO', -8.8390, 13.2894, 8900],
        // West Africa
        ['Lagos', 'NG', 6.5244, 3.3792, 15000], ['Abuja', 'NG', 9.0765, 7.3986, 3800], ['Port Harcourt', 'NG', 4.8156, 7.0498, 3000],
        ['Kano', 'NG', 12.0022, 8.592, 4200], ['Ibadan', 'NG', 7.3775, 3.947, 3600], ['Abidjan', 'CI', 5.36, -4.0083, 5600],
        ['Yamoussoukro', 'CI', 6.8276, -5.2893, 360], ['Dakar', 'SN', 14.7167, -17.4677, 3400], ['Accra', 'GH', 5.6037, -0.187, 2600],
        ['Kumasi', 'GH', 6.6885, -1.6244, 3500], ['Cotonou', 'BJ', 6.3654, 2.4183, 700], ['Lomé', 'TG', 6.1256, 1.2254, 1900],
        ['Bamako', 'ML', 12.6392, -8.0029, 2800], ['Ouagadougou', 'BF', 12.3714, -1.5197, 3000], ['Conakry', 'GN', 9.6412, -13.5784, 2000],
        ['Niamey', 'NE', 13.5116, 2.1254, 1400], ['Freetown', 'SL', 8.4657, -13.2317, 1200], ['Monrovia', 'LR', 6.3156, -10.8074, 1600],
        ['Nouakchott', 'MR', 18.0735, -15.9582, 1300],
        // Rest of Africa
        ['Nairobi', 'KE', -1.2921, 36.8219, 4700], ['Mombasa', 'KE', -4.0435, 39.6682, 1200], ['Dar es Salaam', 'TZ', -6.7924, 39.2083, 7000],
        ['Kampala', 'UG', 0.3476, 32.5825, 3600], ['Kigali', 'RW', -1.9441, 30.0619, 1200], ['Addis Ababa', 'ET', 8.9806, 38.7578, 5200],
        ['Johannesburg', 'ZA', -26.2041, 28.0473, 6000], ['Cape Town', 'ZA', -33.9249, 18.4241, 4700], ['Durban', 'ZA', -29.8587, 31.0218, 3900],
        ['Casablanca', 'MA', 33.5731, -7.5898, 3700], ['Rabat', 'MA', 34.0209, -6.8416, 1900], ['Algiers', 'DZ', 36.7538, 3.0588, 3400],
        ['Tunis', 'TN', 36.8065, 10.1815, 2400], ['Cairo', 'EG', 30.0444, 31.2357, 21000], ['Lusaka', 'ZM', -15.3875, 28.3228, 3000],
        ['Harare', 'ZW', -17.8252, 31.0335, 1600], ['Maputo', 'MZ', -25.9692, 32.5732, 1100], ['Antananarivo', 'MG', -18.8792, 47.5079, 3500],
        // Europe
        ['Paris', 'FR', 48.8566, 2.3522, 11000], ['Lyon', 'FR', 45.764, 4.8357, 1700], ['Marseille', 'FR', 43.2965, 5.3698, 1600],
        ['Lille', 'FR', 50.6292, 3.0573, 1200], ['Toulouse', 'FR', 43.6047, 1.4442, 1000], ['Bordeaux', 'FR', 44.8378, -0.5792, 950],
        ['Nantes', 'FR', 47.2184, -1.5536, 970], ['Strasbourg', 'FR', 48.5734, 7.7521, 800], ['Nice', 'FR', 43.7102, 7.262, 950],
        ['Le Havre', 'FR', 49.4944, 0.1079, 240], ['Rouen', 'FR', 49.4432, 1.0999, 670], ['Montpellier', 'FR', 43.6108, 3.8767, 800],
        ['Saint-Denis', 'FR', 48.9362, 2.3574, 110], ['Brussels', 'BE', 50.8503, 4.3517, 2100], ['Antwerp', 'BE', 51.2194, 4.4025, 1200],
        ['Liège', 'BE', 50.6326, 5.5797, 750], ['Geneva', 'CH', 46.2044, 6.1432, 600], ['Zurich', 'CH', 47.3769, 8.5417, 1400],
        ['Lausanne', 'CH', 46.5197, 6.6323, 420], ['Luxembourg', 'LU', 49.6116, 6.1319, 130], ['London', 'GB', 51.5072, -0.1276, 9500],
        ['Manchester', 'GB', 53.4808, -2.2426, 2800], ['Birmingham', 'GB', 52.4862, -1.8904, 2900], ['Dublin', 'IE', 53.3498, -6.2603, 1400],
        ['Berlin', 'DE', 52.52, 13.405, 3700], ['Frankfurt', 'DE', 50.1109, 8.6821, 2300], ['Hamburg', 'DE', 53.5511, 9.9937, 1900],
        ['Munich', 'DE', 48.1351, 11.582, 1500], ['Amsterdam', 'NL', 52.3676, 4.9041, 1200], ['Rotterdam', 'NL', 51.9244, 4.4777, 1000],
        ['Madrid', 'ES', 40.4168, -3.7038, 6700], ['Barcelona', 'ES', 41.3874, 2.1686, 5600], ['Lisbon', 'PT', 38.7223, -9.1393, 2900],
        ['Rome', 'IT', 41.9028, 12.4964, 4300], ['Milan', 'IT', 45.4642, 9.19, 3200], ['Vienna', 'AT', 48.2082, 16.3738, 1900],
        ['Copenhagen', 'DK', 55.6761, 12.5683, 1300], ['Stockholm', 'SE', 59.3293, 18.0686, 1600], ['Oslo', 'NO', 59.9139, 10.7522, 1000],
        ['Warsaw', 'PL', 52.2297, 21.0122, 1800], ['Athens', 'GR', 37.9838, 23.7275, 3200],
        // North America
        ['New York', 'US', 40.7128, -74.006, 18800], ['Houston', 'US', 29.7604, -95.3698, 7100], ['Atlanta', 'US', 33.749, -84.388, 6100],
        ['Washington', 'US', 38.9072, -77.0369, 5400], ['Los Angeles', 'US', 34.0522, -118.2437, 12500], ['Chicago', 'US', 41.8781, -87.6298, 8900],
        ['Dallas', 'US', 32.7767, -96.797, 7600], ['Miami', 'US', 25.7617, -80.1918, 6100], ['Boston', 'US', 42.3601, -71.0589, 4900],
        ['Philadelphia', 'US', 39.9526, -75.1652, 6200], ['Baltimore', 'US', 39.2904, -76.6122, 2800], ['Minneapolis', 'US', 44.9778, -93.265, 3700],
        ['Seattle', 'US', 47.6062, -122.3321, 4000], ['San Francisco', 'US', 37.7749, -122.4194, 4700], ['Charlotte', 'US', 35.2271, -80.8431, 2700],
        ['Columbus', 'US', 39.9612, -82.9988, 2100], ['Newark', 'US', 40.7357, -74.1724, 2100], ['Silver Spring', 'US', 38.9907, -77.0261, 80],
        ['Montreal', 'CA', 45.5019, -73.5674, 4300], ['Toronto', 'CA', 43.6532, -79.3832, 6400], ['Ottawa', 'CA', 45.4215, -75.6972, 1400],
        ['Quebec City', 'CA', 46.8139, -71.208, 840], ['Vancouver', 'CA', 49.2827, -123.1207, 2600], ['Calgary', 'CA', 51.0447, -114.0719, 1500],
        // Asia and Middle East
        ['Dubai', 'AE', 25.2048, 55.2708, 3500], ['Abu Dhabi', 'AE', 24.4539, 54.3773, 1500], ['Guangzhou', 'CN', 23.1291, 113.2644, 18000],
        ['Shanghai', 'CN', 31.2304, 121.4737, 24000], ['Shenzhen', 'CN', 22.5431, 114.0579, 17000], ['Yiwu', 'CN', 29.3069, 120.0751, 1900],
        ['Hong Kong', 'HK', 22.3193, 114.1694, 7500], ['Istanbul', 'TR', 41.0082, 28.9784, 15600], ['Mumbai', 'IN', 19.076, 72.8777, 20000],
        ['Delhi', 'IN', 28.7041, 77.1025, 31000], ['Doha', 'QA', 25.2854, 51.531, 1200], ['Riyadh', 'SA', 24.7136, 46.6753, 7600],
        ['Jeddah', 'SA', 21.4858, 39.1925, 4700], ['Beirut', 'LB', 33.8938, 35.5018, 2400], ['Tokyo', 'JP', 35.6762, 139.6503, 37000],
        ['Singapore', 'SG', 1.3521, 103.8198, 5900],
        // Latin America and Caribbean
        ['Mexico City', 'MX', 19.4326, -99.1332, 21800], ['São Paulo', 'BR', -23.5505, -46.6333, 22400], ['Rio de Janeiro', 'BR', -22.9068, -43.1729, 13600],
        ['Bogotá', 'CO', 4.711, -74.0721, 11300], ['Port-au-Prince', 'HT', 18.5944, -72.3074, 2800],
    ];

    public function run(): void
    {
        if (City::query()->exists()) {
            return;
        }

        $rows = array_map(fn (array $city) => [
            'name' => $city[0],
            'ascii_name' => Str::lower(Str::ascii($city[0])),
            'country' => $city[1],
            'lat' => $city[2],
            'lon' => $city[3],
            'population' => $city[4] * 1000,
        ], self::CITIES);

        City::query()->insert($rows);
    }
}

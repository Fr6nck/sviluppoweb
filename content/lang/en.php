<?php
/**
 * English — same register as the Italian: direct, concrete, no tourist
 * superlatives. Proper names are not translated: Arco del Vento, Via Santa
 * Maria delle Rose, San Rufino, Piazza del Comune stay as they are.
 *
 * Any key missing here falls back to the Italian one, so a partially
 * translated site stays usable instead of showing raw keys.
 */

declare(strict_types=1);

return [
    'common' => [
        'brand'            => 'Arco del Vento',
        'brand_full'       => 'Arco del Vento di Pecetta Daniele',
        'to_confirm'       => '[to confirm]',
        'price_to_confirm' => '[rate to confirm]',
        'demo_asset'       => '[placeholder image]',
        'skip'             => 'Skip to content',
        'menu_open'        => 'Open the menu',
        'menu_close'       => 'Close the menu',
        'menu'             => 'Menu',
        'language'         => 'Language',
        'per_night'        => 'per night',
        'from'             => 'from',
        'night'            => 'night',
        'nights'           => 'nights',
        'guest'            => 'guest',
        'guests'           => 'guests',
        'optional'         => 'optional',
        'yes'              => 'Yes',
        'no'               => 'No',
        'required_note'    => 'Every field without the «optional» note is needed.',
        'back'             => 'Go back',
        'close'            => 'Close',
        'loading'          => 'One moment',
        'read_more'        => 'Keep reading',
    ],

    'date' => [
        'months' => [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December',
        ],
        'weekdays_short' => ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
    ],

    'nav' => [
        'label'    => 'Main',
        'home'     => 'Home',
        'rooms'    => 'Rooms',
        'property' => 'The house',
        'assisi'   => 'Assisi on foot',
        'info'     => 'Information',
        'contact'  => 'Contact',
        'book'     => 'Book now',
        'privacy'  => 'Privacy',
        'topbar' => [
            'center'  => 'In the old town of Assisi',
            'host'    => 'The owner welcomes you',
            'parking' => 'Parking :metres m away',
            'since'   => 'Open since 2002',
        ],
    ],

    'cta' => [
        'book'         => 'Book now',
        'book_room'    => 'Book this room',
        'check'        => 'Check availability',
        'whatsapp'     => 'WhatsApp',
        'write'        => 'Write to us',
        'call'         => 'Call',
        'see_rooms'    => 'See the rooms',
        'see_room'     => 'See the room',
        'all_rooms'    => 'All five rooms',
        'see_assisi'   => 'Assisi on foot',
        'see_info'     => 'Practical information',
        'see_property' => 'Inside the house',
        'send'         => 'Send the request',
        'continue'     => 'Continue',
    ],

    'home' => [
        'seo_title'       => 'Arco del Vento — guest rooms in Assisi, five rooms in the old town',
        'seo_description' => 'Five rooms in a house in Assisi, on Via Santa Maria delle Rose. Run by '
                           . 'Daniele Pecetta since 2002. Book direct.',

        'trust' => [
            'Direct booking',
            'No platform commission',
            'Daniele answers in person',
        ],

        'hero' => [
            'eyebrow' => 'GUEST ROOMS SINCE 2002',
            'title'   => 'Five rooms,|one house in',
            'sign'    => 'Assisi.',
            'lead'    => 'In the old town, next to San Rufino.|Five rooms, looked after in person by Daniele.',
            'place'   => 'Assisi, Umbria',
            'scroll'  => 'Go to the rooms',
            'image_alt' => 'A steep lane in the old town of Assisi, with a stone bell tower in the background.',
            'image_credit' => 'Assisi, old town — photograph by Niels Baars, Unsplash',
        ],

        'slides' => [
            'label'     => 'Photographs of Assisi and of the house',
            'prev'      => 'Previous photograph',
            'next'      => 'Next photograph',
            'pause'     => 'Pause the photographs',
            'play'      => 'Play the photographs',
            'valle'     => 'THE VALLEY, SEEN FROM ASSISI.',
            'vicolo'    => 'THE OLD TOWN, ON FOOT.',
            'basilica'  => 'SAN FRANCESCO, AT SUNSET.',
            'corridoio' => 'THE COMPASS ROSE, IN THE HALLWAY.',
            'camera'    => 'ONE OF THE FIVE ROOMS.',
        ],

        'manifesto' => [
            'text'   => 'Five rooms.|One person handing you the keys,',
            'sign'   => 'since 2002.',
            'author' => 'GUEST ROOMS|IN THE OLD TOWN OF ASSISI',
        ],

        'rooms' => [
            'eyebrow'  => 'THE ROOMS OF THE HOUSE',
            'title'    => 'The five rooms|of the',
            'sign'     => 'house.',
            'lead'     => 'Five rooms, all in the same house. What tells them apart are the beds, which way '
                        . 'they face and how much space you get.',
            'discover' => 'All the rooms',
            'prev'     => 'Previous rooms',
            'next'     => 'Next rooms',
            'tagline'  => 'FIVE ROOMS, ONE HOUSE.',
        ],

        'position' => [
            'eyebrow'  => 'THE OLD TOWN, ON FOOT',
            'title'    => 'The city starts|outside the',
            'sign'     => 'door.',
            'lead'     => 'The house is on Via Santa Maria delle Rose, inside the old town. You will not need '
                        . 'the car: everything you came to see is within walking distance.',
            'rufino_text' => 'San Rufino is the church of this quarter and the cathedral of the city: '
                           . 'Francis and Clare were both baptised at its font. It is the closest landmark '
                           . 'to the house, and the first bell tower you learn to recognise.',
            'chip'     => 'The old town',
            'vertical' => 'ASSISI, ONE STEP AT A TIME',
            'new_tab'  => 'on Google Maps, in a new tab',
            'frame_alt'     => 'The Umbrian valley seen from above, with cultivated fields and a village in the distance.',
        ],

        'reviews' => [
            'title'   => 'What past guests say',
            'booking' => 'Booking',
            'google'  => 'Google',
            'count'   => ':count reviews on :platform',
        ],

        'host' => [
            'eyebrow' => 'WHO KEEPS IT OPEN',
            'title'   => 'The house is run|by one',
            'sign'    => 'person.',
            'lead'    => 'No front desk, no room service, no call centre. There is Daniele, who opened in '
                       . '2002 and has kept it open ever since.',
            'numbers' => [
                ['value' => '05',   'name' => 'ROOMS'],
                ['value' => '2002', 'name' => 'OPEN SINCE'],
                ['value' => '01',   'name' => 'PERSON RUNNING IT'],
            ],
        ],

        'arrive' => [
            'eyebrow'         => 'GETTING HERE',
            'map'             => 'Open the map',
            'navigator_label' => 'In the sat nav',
            'parking_label'   => 'Parking',
            'parking_value'   => 'Piazza Matteotti, :metres m',
            'train_label'     => 'By train',
            'train_value'     => 'Assisi station, then the bus',
            'plane_label'     => 'By plane',
            'plane_value'     => 'Perugia airport, then Airlink',
        ],

        'walk' => [
            'image_alt' => 'The Basilica di San Francesco at sunset, its pale stone façade lit from the side.',
            'image_credit' => 'Basilica di San Francesco — photograph by Alessandro Guarino, Unsplash',
        ],

        'faq' => [
            'eyebrow' => 'THE USUAL QUESTIONS',
            'title'   => 'What people ask',
            'sign'    => 'first.',
        ],

        'closing' => [
            'eyebrow' => 'DIRECT BOOKING',
            'title'   => 'Ask for the dates|you',
            'sign'    => 'need.',
            'lead'    => 'The request goes straight to Daniele — no agency in between, no platform '
                       . 'commission. And the answer comes from Daniele too.',
        ],
    ],

    'rooms' => [
        'seo_title'       => 'The five rooms — Arco del Vento, Assisi',
        'seo_description' => 'The five rooms of Arco del Vento, guest rooms on Via Santa Maria delle Rose in '
                           . 'Assisi. Beds, occupancy, amenities and rates.',
        'eyebrow'   => 'THE ROOMS',
        'title'     => 'Five rooms,|one',
        'sign'      => 'house.',
        'lead'      => 'There are five and they are all in the same building. Below you will find the beds, '
                     . 'the maximum occupancy and the amenities of each one; availability is checked with dates.',
        'image_alt' => 'Placeholder: the photograph of the house is not available yet.',
        'corridor_alt' => 'The corridor of the house, with a parquet floor and a compass rose inlaid '
                        . 'in the wood; framed views of Assisi on the walls.',
        'corridor_caption' => 'The compass rose in the corridor floor. It is the one from the mark, '
                            . 'and it was there first.',

        'demo_notice_title' => 'What is still missing',
        'demo_notice_text'  => 'Rates, room types, beds, occupancy and floor are the real ones, and the '
                             . 'photographs are of the house itself. Still to be written: the room sizes. '
                             . 'Room 01 — the triple — is the only one still without a photograph.',

        'list_title'    => 'One by',
        'list_sign'     => 'one.',
        'rates_title'   => 'The rates side by side',
        'price_on_dates' => 'rate with your dates',
        'price_on_dates_long' => 'The rate depends on your dates and on how many of you there are: '
                               . 'pick the period and you will see it, with no account and no commitment.',
        'rates_hidden'  => 'Rates change with the season and with how many people sleep in the room, so '
                         . 'there is no fixed price list on the page: choose your dates and see the real '
                         . 'price for those nights. No account needed, and it commits you to nothing.',
        'rates_note'    => 'The price is for the room, not per person: a double room used by one '
                         . 'person costs less.',
        'rates_caption' => 'Rate per night for the room, according to how many people sleep in it. '
                         . 'City tax not included. Any seasonal variation is still to be confirmed.',
        'table' => [
            'room'      => 'Room',
            'type'      => 'Type',
            'occupancy' => 'Guests',
            'beds'      => 'Beds',
            'rate'          => 'Per night',
            'not_available' => 'not available for this number of guests',
        ],
        'card' => [
            'layouts'      => 'Layouts',
            'rate'         => 'Rate',
            'photo_soon'   => 'Photograph coming soon',
            'guests_one'   => '1 guest',
            'guests_up_to' => 'Up to :count guests',
        ],
        'status' => [
            'free'     => 'AVAILABLE',
            'last'     => 'LAST DATES',
            'busy'     => 'BOOKED',
            'to_check' => 'AVAILABILITY TO BE CHECKED',
        ],
    ],

    'room' => [
        'seo_description' => ':name — Arco del Vento guest rooms, Via Santa Maria delle Rose, Assisi. '
                           . 'Beds, occupancy, amenities and direct booking.',
        'eyebrow'       => 'ROOM',
        'gallery'       => 'The room',
        'gallery_note'  => 'Photographs of this room are not available yet.',
        'photo_pending' => 'This room is still waiting for its own photograph.',
        'description'   => 'The room',
        'characteristics' => 'Characteristics',
        'amenities'     => 'Amenities',
        'bathroom'      => 'The bathroom',
        'view'          => 'Which way it faces',
        'rate'          => 'Rate',
        'other_rooms'   => 'The other rooms',
        'name_to_confirm' => 'Room name',
        'meta' => [
            'occupancy' => 'Guests',
            'beds'      => 'Beds',
            'layouts'   => 'Bed layouts',
            'size'      => 'Size',
            'floor'     => 'Floor',
        ],
        'not_found_title' => 'This room does not exist',
        'not_found_text'  => 'The address you followed does not match any of the five rooms. They are all '
                           . 'in the list.',
    ],

    'room_types' => [
        'single'       => 'Single',
        'double'       => 'Double',
        'double-extra' => 'Double with an extra bed',
        'double-twin'  => 'Double, beds can be separated',
        'single-double' => 'Single use of a double room',
        'twin'         => 'Twin',
        'triple'       => 'Triple',
    ],

    'beds' => [
        'double' => ['one double bed', ':count double beds'],
        'single' => ['one single bed', ':count single beds'],
        'sofa'   => ['one sofa bed', ':count sofa beds'],
    ],

    'layouts' => [
        'single' => 'single use',
        'double' => 'double bed',
        'twin'   => 'two separate beds',
        'triple' => 'triple',
    ],

    'views' => [
        'demo'       => 'Aspect to be confirmed',
        'piazza'     => 'Onto Piazza San Rufino and the cathedral',
    ],

    'amenities' => [
        'private-bathroom' => 'Private bathroom',
        'wifi'             => 'Wi-Fi',
        'linen'            => 'Bed linen',
        'towels'           => 'Towels',
        'desk'             => 'Desk',
        'wardrobe'         => 'Wardrobe',
        'kettle'           => 'Kettle',
        'minibar'          => 'Mini fridge on request',
        'fan'              => 'Fan',
        'heating'          => 'Heating',
    ],

    'bathroom' => [
        'private' => 'Private bathroom',
        'shared'  => 'Shared bathroom',
        'shower'  => 'Shower',
        'bathtub' => 'Bathtub',
    ],

    'property' => [
        'seo_title'       => 'The house — Arco del Vento, guest rooms in Assisi',
        'seo_description' => 'Five rooms on Via Santa Maria delle Rose in Assisi. Guest rooms run in person '
                           . 'by Daniele Pecetta since 2002.',
        'eyebrow' => 'THE HOUSE',
        'title'   => 'One house, five rooms,|one',
        'sign'    => 'person.',
        'lead'    => 'Arco del Vento rents rooms: five of them, in a house on Via Santa Maria delle Rose in '
                   . 'Assisi, let one at a time to whoever is passing through. It is not a hotel and does '
                   . 'not try to look like one.',
        'image_alt' => 'Placeholder: the photograph of the house is not available yet.',

        'what_title' => 'What «affittacamere» means',
        'what_text'  => 'It means the rooms are few and they are inside a real house, that there is no desk '
                      . 'in the hall, and that whoever opens the door is the same person who decides how '
                      . 'things are done. It also means some things a hotel takes for granted are not here: '
                      . 'they are listed, plainly, under practical information.',

        'host_title' => 'Daniele',
        'host_text'  => 'Daniele Pecetta opened Arco del Vento in 2002 and has run it himself since. He '
                      . 'answers the phone, hands over the keys and keeps the five rooms in order. When you '
                      . 'write to this address, he is the one reading.',
        'host_role'  => 'OWNER · SINCE 2002',
        'host_bio'   => 'He opened the guest rooms in 2002 on Via Santa Maria delle Rose and has kept them '
                      . 'open in person for twenty-three years: five rooms, no staff, no front desk.',
        'host_more'  => 'The rest of the story of the house — how it was before, what was rebuilt and when',

        'building_title' => 'The building',
        'building_text'  => 'What we know about the house is that the rooms are on the second floor, reached '
                          . 'by two flights of stairs. Its age, its materials, what it was before it took in '
                          . 'guests: those we do not, and they are exactly the things a guest remembers. '
                          . 'They have to be told by whoever knows them.',

        'city_title' => 'Staying inside the city',
        'city_text'  => 'Sleeping in the old town is not the same as sleeping out of town with a shuttle: '
                      . 'you hear the bells, the streets climb, and the car, if you bring one, gets left '
                      . 'where it can be left. In exchange, in the morning you are already where everyone '
                      . 'else is still arriving.',
    ],

    'assisi' => [
        'seo_title'       => 'Assisi on foot — Arco del Vento',
        'seo_description' => 'What you can reach on foot from Via Santa Maria delle Rose: San Rufino, Santa '
                           . 'Chiara, San Francesco, Piazza del Comune, the Spoliazione, the Carceri.',
        'eyebrow' => 'ON FOOT',
        'title'   => 'Everything there is,|without getting in the',
        'sign'    => 'car.',
        'lead'    => 'The house is inside the old town of Assisi: from there you walk, uphill and down, and '
                   . 'distance is measured in minutes rather than kilometres.',
        'image_alt' => 'A steep lane in the old town of Assisi, with a stone bell tower in the background.',

        'places_title' => 'The six places you reach on foot',
        'places_note'  => 'Walking times have to be checked on the ground before publication. Until they '
                        . 'are, they stay marked.',
        'walk_label'   => 'on foot',

        'rufino_title' => 'Why San Rufino comes first',
        'rufino_text'  => 'Because it is the cathedral of the city and the church of this quarter, and '
                        . 'because it is the least crowded of the three great ones: you walk in, you look at '
                        . 'the font where Francis and Clare were baptised, and you come out into a square '
                        . 'where you can still sit down.',

        'moving_title' => 'Getting around',
        'moving_text'  => 'The old town is walked end to end. Arriving by train you get off at Santa Maria '
                        . 'degli Angeli, the station for Assisi, and come up by bus or taxi; arriving by car '
                        . 'you leave it in the car parks below the walls and do the last stretch on foot or '
                        . 'by the escalators.',
        'moving_note'  => 'Lines, timetables and the car park we recommend for this house',
    ],

    'places' => [
        'san-rufino'    => ['name' => 'Cattedrale di San Rufino',    'note' => 'the cathedral, and this quarter’s church'],
        'santa-chiara'  => ['name' => 'Basilica di Santa Chiara',    'note' => 'down the slope, to the east'],
        'comune'        => ['name' => 'Piazza del Comune',           'note' => 'the temple of Minerva and the cafés'],
        'spoliazione'   => ['name' => 'Santuario della Spoliazione', 'note' => 'beside the bishop’s palace'],
        'san-francesco' => ['name' => 'Basilica di San Francesco',   'note' => 'at the far end of the old town'],
        'carceri'       => ['name' => 'Eremo delle Carceri',         'note' => 'outside town, uphill on Monte Subasio'],
    ],

    'info' => [
        'seo_title'       => 'Practical information — Arco del Vento, Assisi',
        'seo_description' => 'Check-in, arrival, parking, stairs, Wi-Fi, pets and house rules. Practical '
                           . 'information for Arco del Vento, guest rooms in Assisi.',
        'eyebrow' => 'INFORMATION',
        'title'   => 'What staying here|actually',
        'sign'    => 'means.',
        'lead'    => 'This is not the site’s secondary page: it is the one people read the night before '
                   . 'they travel. Where a fact is missing it is marked, not rounded off.',

        'notice_title' => 'Why so much is marked',
        'notice_text'  => 'Arrival times, parking, stairs, amenities and house rules come from Daniele '
                        . 'himself, and you read them as he gave them. What is still missing stays marked: '
                        . 'the check-out time, how payment and cancellation work, the walking times to the '
                        . 'six places. A wrong time on a website is one more phone call and one guest fewer, '
                        . 'so we would rather leave it blank.',

        'sections' => [
            'arrival'  => 'Before you arrive',
            'getting'  => 'Getting here',
            'stay'     => 'The stay',
            'house'    => 'The house',
            'rules'    => 'Rules',
            'contact'  => 'Contact',
        ],

        'items' => [
            'check_in'      => 'Check-in time',
            'check_out'     => 'Check-out time',
            'welcome'       => 'Who lets you in',
            'documents'     => 'Documents',
            'contact_hours' => 'When we answer',
            'navigator'     => 'What to put in the sat-nav',
            'taxi'          => 'By taxi',
            'plane'         => 'From the airport',
            'kettle'        => 'Kettle',
            'minibar'       => 'Mini fridge',
            'fans'          => 'Fans',
            'air_conditioning' => 'Air conditioning',
            'rooms'         => 'How many rooms',
            'floor'         => 'Which floor',
            'common_areas'  => 'Shared spaces',
            'open'          => 'When we are open',
            'guest_contact' => 'Booking for someone else',
            'late_arrival'  => 'Arriving outside those hours',
            'parking'       => 'Parking',
            'car'           => 'Arriving by car',
            'train'         => 'Arriving by train',
            'bus'           => 'Buses and escalators',
            'stairs'        => 'Stairs',
            'lift'          => 'Lift',
            'wifi'          => 'Wi-Fi',
            'breakfast'     => 'Breakfast',
            'heating'       => 'Heating',
            'cleaning'      => 'Cleaning',
            'linen'         => 'Change of linen',
            'pets'          => 'Pets',
            'smoking'       => 'Smoking',
            'children'      => 'Children',
            'city_tax'      => 'City tax',
            'min_nights'    => 'Minimum stay',
            'payment'       => 'Payment and deposit',
            'cancellation'  => 'Cancellation',
            'languages'     => 'Languages spoken',
            'accessibility' => 'Accessibility',
        ],

        'known' => [
            'welcome'       => 'Daniele, in person: he hands over the keys and shows you the house.',
            'documents'     => 'At check-in, or send them ahead on WhatsApp and it goes quicker.',
            'min_nights'    => 'A stay that includes a Saturday night is at least :nights nights.',
            'navigator'     => 'Set :place, not the street address: the street is a dead end. On foot the exact address works.',
            'car'           => 'You cannot drive right up to the door: you leave the car and walk the last stretch.',
            'train'         => 'Assisi station, then AssisiLink or line C up to Piazza Matteotti.',
            'plane'         => 'Perugia airport, Airlink service: about four runs a day, arriving at Piazza Matteotti.',
            'taxi'          => 'Ask to be taken to :place.',
            'bus_note'      => 'Lines, current timetables and the connection to the Eremo delle Carceri',
            'no_meals'      => 'There is none: we serve no meals, and no breakfast either.',
            'wifi'          => 'Yes, around :speed — enough to work on.',
            'city_tax'      => ':amount per person per night, for the first :nights nights. Under :age are exempt. Paid at check-in.',
            'rooms'         => 'Five, all with a private bathroom.',
            'common_areas'  => 'The entrance is shared with the building and the corridor is a passageway.',
            'open_all_year' => 'All year round.',
            'no_smoking'    => 'No smoking in the rooms.',
            'guest_contact' => 'If you book for someone else, leave us a direct contact for whoever is sleeping here.',
            'remote_work'   => 'The connection is good enough to work on.',
            'keys'          => 'Daniele hands over the keys in person.',
            'address'       => 'Via Santa Maria delle Rose 1/A, Assisi.',
            'since'         => 'Open since 2002.',
            'direct'        => 'Booking is direct: the request reaches Daniele, not a platform.',
        ],
    ],

    'book' => [
        'seo_title'       => 'Book — Arco del Vento, Assisi',
        'seo_description' => 'Check your dates and send a booking request to Arco del Vento, guest rooms on '
                           . 'Via Santa Maria delle Rose in Assisi.',
        'eyebrow' => 'BOOK',
        'title'   => 'The dates first,|then the',
        'sign'    => 'rest.',
        'lead'    => 'Four steps: the dates, the room, your details, the request. Nothing is paid online '
                   . 'and no account is needed.',

        'demo_title' => 'The dates are a demo, the rates are not',
        'demo_text'  => 'The prices you see are the real ones, and the total is worked out from them. '
                      . 'Which dates come up as free, though, is decided by a demo provider built into '
                      . 'the site rather than a real calendar — and the request does not reach any inbox '
                      . 'yet.',

        'steps' => [
            'dates'   => 'Dates and guests',
            'rooms'   => 'Available rooms',
            'details' => 'Your details',
            'done'    => 'Request sent',
        ],
        'step_of' => 'Step :current of :total',

        'search' => [
            'legend'    => 'Search availability',
            'arrival'   => 'Arrival',
            'departure' => 'Departure',
            'guests'    => 'Guests',
            'submit'    => 'Check availability',
            'note'      => 'Minimum stay :nights nights. City tax not included, still to be confirmed.',
            'note_no_min' => 'The price depends on how many people sleep in the room. City tax of €3 '
                           . 'per person per night, for the first three nights, paid at check-in.',
            'note_stay' => ':nights nights · :guests',
        ],

        'results' => [
            'title'      => 'The rooms that are free',
            'for_dates'  => 'From :from to :to, :guests.',
            'none_title' => 'These dates are not free',
            'none_text'  => 'None of the five rooms is available for the period you asked for. Try moving a '
                          . 'few days either way, or write to Daniele: sometimes something frees up before '
                          . 'the calendar catches it.',
            'nearest'    => 'The nearest free dates:',
            'too_many_title' => 'We do not have a room for :guests people',
            'too_many_text'  => 'The largest room sleeps :max. A bigger group needs two rooms: write to us '
                              . 'with your dates and Daniele will tell you what can be done.',
            'choose'     => 'Choose this room',
            'total'      => 'Total for :nights nights',
            'per_night'  => ':amount per night',
            'change'     => 'Change the dates',
            'unavailable_room' => 'Not free on these dates',
        ],

        'details' => [
            'title'      => 'Your details',
            'summary'    => 'Summary',
            'legend'     => 'Who is arriving',
            'first_name' => 'First name',
            'last_name'  => 'Surname',
            'email'      => 'Email',
            'phone'      => 'Phone',
            'country'    => 'Country of residence',
            'notes'      => 'Anything we should know',
            'notes_help' => 'Your likely arrival time, an extra bed, an allergy. Put it here.',
            'privacy'    => 'I have read how my data is handled.',
            'submit'     => 'Send the request',
            'change_room' => 'Change room',
        ],

        'done' => [
            'title'     => 'The request has gone',
            'text'      => 'Daniele has it and will answer himself. Below is a summary of what you asked '
                         . 'for: worth keeping.',
            'reference' => 'Reference',
            'next'      => 'What happens now',
            'next_text' => 'A request is not yet a confirmed booking: it becomes one when Daniele writes '
                         . 'back that the room is held for you.',
            'demo_note' => 'This is a prototype: no email was actually sent. The message was written to the '
                         . 'site’s log instead.',
            'home'      => 'Back to the home page',
        ],

        'summary' => [
            'room'       => 'Room',
            'dates'      => 'Dates',
            'nights'     => 'Nights',
            'guests'     => 'Guests',
            'rate'       => 'Rate per night',
            'total'      => 'Indicative total',
            'total_note' => 'Daniele confirms the final total.',
        'city_tax'   => 'City tax',
        'city_tax_note' => ':amount per person per night, for the first :nights nights. Under :age '
                         . 'are exempt. Paid at check-in together with the balance.',
        'city_tax_upto' => 'up to :amount',
        ],
    ],

    'contact' => [
        'seo_title'       => 'Contact — Arco del Vento, Assisi',
        'seo_description' => 'Write to Arco del Vento, guest rooms on Via Santa Maria delle Rose in Assisi. '
                           . 'Daniele Pecetta answers.',
        'eyebrow' => 'CONTACT',
        'title'   => 'Write,|and a person',
        'sign'    => 'answers.',
        'lead'    => 'There is no switchboard and no form that routes anywhere: what you write, Daniele '
                   . 'reads.',

        'where_title' => 'Where we are',
        'how_title'   => 'How to reach us',
        'form_title'  => 'Write to us',

        'form' => [
            'legend'   => 'Your message',
            'name'     => 'Name',
            'email'    => 'Email',
            'phone'    => 'Phone',
            'subject'  => 'Subject',
            'subjects' => [
                'info'    => 'A question',
                'booking' => 'A booking',
                'arrival' => 'My arrival',
                'other'   => 'Something else',
            ],
            'message'      => 'Message',
            'message_help' => 'The dates you have in mind, how many of you there are, what you need to know.',
            'privacy'      => 'I have read how my data is handled.',
            'submit'       => 'Send the message',
        ],

        'success_title' => 'Message sent',
        'success_text'  => 'Thank you: the message arrived. Daniele will answer.',
        'demo_note'     => 'This is a prototype: no email was actually sent. The message was written to the '
                         . 'site’s log instead.',
        'error_title'   => 'The form did not go through',
        'error_text'    => 'Check the fields marked below and try again.',
    ],

    'privacy' => [
        'seo_title'       => 'Privacy — Arco del Vento',
        'seo_description' => 'What data this site collects, why, for how long, and how to ask for it to '
                           . 'be deleted.',
        'eyebrow' => 'PRIVACY',
        'title'   => 'What we know|about',
        'sign'    => 'you.',
        'lead'    => 'Little, and only what is needed to answer. This page says all of it: without it '
                   . 'the tick-boxes in the forms mean nothing.',

        'blocks' => [
            'who' => [
                'title' => 'Who handles the data',
                'text'  => 'The data controller is Arco del Vento di Pecetta Daniele, Via Santa Maria '
                         . 'delle Rose 1/A, Assisi. The address to write to about privacy matters, which '
                         . 'may differ from the general one, belongs here.',
            ],
            'what' => [
                'title' => 'What we collect',
                'text'  => 'From the contact form: name, email, phone if you give one, and the text of '
                         . 'your message. From a booking request: first name and surname, email, phone if '
                         . 'given, country of residence, dates, room and the notes you write. Nothing '
                         . 'else: there are no hidden fields collecting anything more.',
            ],
            'why' => [
                'title' => 'Why',
                'text'  => 'To answer you and to handle the request for a stay. We do not use your data '
                         . 'to send you advertising, we do not sell it and we do not pass it to third '
                         . 'parties beyond what it takes to run the site and the mail.',
            ],
            'how_long' => [
                'title' => 'For how long',
                'text'  => 'The retention period for messages and requests has to be decided and stated '
                         . 'here. Tax obligations and guest-registration rules set their own, longer, '
                         . 'terms.',
            ],
            'cookies' => [
                'title' => 'Cookies and statistics',
                'text'  => 'This site uses one cookie, a technical one: it keeps your session open while '
                         . 'you fill in a form and protects that form against forged submissions. There '
                         . 'are no profiling cookies, no Google Analytics, no advertising pixels. The '
                         . 'typefaces and the images are served from our own server, so no third-party '
                         . 'domain sees your IP address while you read. That is why there is no consent '
                         . 'banner: there would be nothing to consent to.',
            ],
            'rights' => [
                'title' => 'Your rights',
                'text'  => 'You can ask to see the data you gave us, to correct it or to have it '
                         . 'deleted, and you can object to it being processed. Write to the address '
                         . 'above. If the answer does not satisfy you, you can turn to the Italian data '
                         . 'protection authority, the Garante per la protezione dei dati personali.',
            ],
        ],

        'notice_title' => 'Text still to be completed',
        'notice_text'  => 'This notice is in place but it is not a finished legal document: the marked '
                        . 'points have to be completed by the owner, and it is worth having an '
                        . 'accountant or a lawyer read it before publication.',
    ],

    'errors' => [
        'required'   => 'This field is needed.',
        'email'      => 'The @ or the domain is missing: check the address.',
        'too_short'  => 'Write a few words more.',
        'too_long'   => 'Too long: shorten the text.',
        'date'       => 'That date does not exist. Use your device’s calendar.',
        'range'      => 'That number will not work.',
        'accepted'   => 'The tick is needed to carry on.',
        'past'       => 'The arrival date has already gone by.',
        'order'      => 'Departure has to come after arrival.',
        'min_nights'   => 'The minimum stay is :nights nights.',
        'saturday_min' => 'Saturday is not booked on its own: a stay that includes a Saturday night is '
                        . 'at least :nights nights. Friday and Saturday works, or Saturday and Sunday.',
        'max_stay'   => 'For stays longer than :nights nights write to us: we arrange those separately.',
        'token'      => 'The page sat open too long. Reload it and try again.',
        'room'       => 'Choose one of the free rooms.',
        'no_session' => 'The path was interrupted. Let us start again from the dates.',
    ],

    'not_found' => [
        'seo_title' => 'Page not found — Arco del Vento',
        'eyebrow'   => 'ERROR 404',
        'title'     => 'This page|does not',
        'sign'      => 'exist.',
        'lead'      => 'The address you followed leads nowhere. The five rooms, the practical information '
                     . 'and the contact page are all one click from here.',
    ],

    'footer' => [
        'where'      => 'WHERE WE ARE',
        'contacts'   => 'CONTACT',
        'stay'       => 'THE STAY',
        'pages'      => 'PAGES',
        'legal_note' => 'The identifiers an Italian accommodation is required to publish — CIN, VAT number — '
                      . 'belong here.',
        'credits'    => 'Prototype. Room content and photographs still to be supplied.',
        'photo_credits' => 'Photographs of the house and the rooms: Arco del Vento. '
                         . 'Views of Assisi: Niels Baars, Gary Walker-Jones, Alessandro Guarino (Unsplash).',
        'rights'     => '© :year Arco del Vento di Pecetta Daniele',
        'tagline'    => 'Five rooms in the old town.|One person keeping them open.',
        'back_top'   => 'Back to top',
    ],

];

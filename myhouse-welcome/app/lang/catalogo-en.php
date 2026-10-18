<?php
/**
 * 9F·11 — The section catalogue in English, for the dashboard when the host writes the guide in a language
 * other than Italian (Pannello::en()). Same keys as SectionCatalog::K: nome / breve / intro, then each field as
 * [label, help] with 'options' and 'sub' where they exist. A missing key keeps the Italian text.
 * Helps that start with "Optional." are split like the Italian «Facoltativo.» (SectionCatalog::facoltativo).
 */
return [
    'checkin' => [
        'intro' => 'How to get in, what guests need on arrival and what to do before leaving. Explain where the keys are and how to open: better not to write door or key-box codes here — tell the guest privately.',
        'fields' => [
            'arrival_mode'   => ['How guests get in', '', 'options' => ['self' => 'Self check-in', 'accoglienza' => 'I welcome them myself', 'cassetta' => 'Key box']],
            'checkin_steps'  => ['Steps to get in', 'One step per line: where the keys are, how the front door opens, which floor.'],
            'late_arrival'   => ['Late arrival', 'Optional. What to do when arriving late, for example after 9 pm.'],
            'documents'      => ['Documents to show', 'Optional. For example: an ID document for every guest, for the mandatory registration.'],
            'tax_amount'     => ['Tourist tax — amount per night', 'Optional. For example: €2.00 per person.'],
            'tax_max_nights' => ['Tourist tax — for how many nights at most', 'Optional. For example: 5.'],
            'tax_notes'      => ['Tourist tax — exemptions and payment', 'Optional. For example: under 14 exempt; in cash on arrival.'],
            'checkin_note'   => ['Important note', 'Optional. It stands out in the guide, in a box.'],
            'checkout_steps' => ['Before leaving', 'One item per line. Tap a suggestion to add it, then change it as you like.'],
            'checkout_notes' => ['Final greeting', 'Optional. A thank-you, a last tip: guests read it when they are about to leave.'],
        ],
    ],
    'wifi' => [
        'breve' => 'Network name and password, copied with one tap.',
        'intro' => 'The network name and the password: guests copy it with one tap, without asking you. If you have more networks, add a row for each one and say which area it covers.',
        'fields' => [
            'networks'        => ['Wi-Fi networks', 'One row per network: 2.4 and 5 GHz, upstairs, annex…', 'sub' => ['ssid' => ['Network name', ''], 'password' => ['Password', ''], 'zone' => ['Area', 'Optional. For example: Main house, Annex.']]],
            'instructions'    => ['Instructions', 'Optional. What to do if the network does not show up.'],
            'router_location' => ['Where the router is', 'Optional. Useful if it needs to be switched off and on again.'],
        ],
    ],
    'services' => [
        'breve' => 'Amenities and the instructions to use them.',
        'intro' => 'What guests find in the property and how it works: tick the amenities and add instructions for the boiler, the washing machine, the hob. Services you offer separately, usually for a fee, go in "Extra services". For the safe, explain how it works, not the code.',
        'fields' => [
            'amenities' => ['Amenities', 'Tick what guests find in the property.'],
            'items'     => ['Other amenities', 'Those not in the list above: in the guide they appear with the others.'],
            'manuals'   => ['Instructions', 'One row for each thing to explain: how to turn on the boiler, how the washing machine works. Title, steps, a photo, a PDF.', 'sub' => ['title' => ['Title', 'For example: The boiler.'], 'steps' => ['Steps', 'One step per line.'], 'photo' => ['Photo', 'Optional.'], 'pdf' => ['PDF', 'Optional. For example: the manual.']]],
            'note'      => ['Note', 'Optional.'],
        ],
    ],
    'riscaldamento' => [
        'breve' => 'When the heating is on, at what temperature, how to adjust it.',
        'intro' => 'Tells guests whether the heating comes on by itself, at what time and temperature, or how to turn it on. In the guide they see at once whether it is on now, with a tip to avoid wasting energy.',
        'fields' => [
            'heating_mode'   => ['Heating', '', 'options' => ['auto' => 'Comes on by itself, on a schedule', 'manuale' => 'Guests turn it on', 'no' => 'There is none']],
            'heating_times'  => ['When it is on', 'One row per time slot: for example 6:30 to 9:00 and 17:00 to 23:00. Without days it applies every day.', 'sub' => ['from' => ['Comes on at', ''], 'to' => ['Goes off at', ''], 'days' => ['Days', 'Optional: empty means every day.']]],
            'heating_temp'   => ['Temperature (°C)', 'Optional. The one set, or the one you recommend. For example: 20.'],
            'heating_period' => ['Time of year', 'Optional. For example: from 15 October to 15 April.'],
            'heating_how'    => ['How to use it', 'Optional. One step per line: where the thermostat is, how to turn it up or down.'],
            'risparmio'      => ['Show the energy-saving tip', 'In the guide, two kind lines: energy is expensive in Italy, windows closed while the heating is on, switch off when going out.'],
            'note'           => ['Note', 'Optional.'],
        ],
    ],
    'aria' => [
        'breve' => 'How to turn on the air conditioning, when it works, at what temperature.',
        'intro' => 'Tells guests how to turn on the air conditioning, or in which hours it works, and the recommended temperature. In the guide they see at once whether it is on now, with a tip to avoid wasting energy.',
        'fields' => [
            'ac_mode'   => ['Air conditioning', '', 'options' => ['libero' => 'Guests turn it on whenever they like', 'orari' => 'It only works at certain times', 'no' => 'There is none']],
            'ac_times'  => ['When it works', 'One row per time slot. Without days it applies every day.', 'sub' => ['from' => ['From', ''], 'to' => ['To', ''], 'days' => ['Days', 'Optional: empty means every day.']]],
            'ac_temp'   => ['Recommended temperature (°C)', 'Optional. For example: 26.'],
            'ac_how'    => ['How to turn it on', 'Optional. One step per line: where the remote is, which button to press.'],
            'risparmio' => ['Show the energy-saving tip', 'In the guide, two kind lines: energy is expensive in Italy, windows closed while the unit is on, switch off when going out.'],
            'note'      => ['Note', 'Optional.'],
        ],
    ],
    'clima' => [
        'breve' => 'When the heating is on, how to use the air conditioning.',
        'intro' => 'Tells guests whether heating and air conditioning come on by themselves, at what time and temperature, or how to turn them on. In the guide they see at once whether they are on now, with a tip to avoid wasting energy.',
        'fields' => [
            'heating_mode'   => ['Heating', '', 'options' => ['auto' => 'Comes on by itself, on a schedule', 'manuale' => 'Guests turn it on', 'no' => 'There is none']],
            'heating_times'  => ['When it is on', 'One row per time slot: for example 6:30 to 9:00 and 17:00 to 23:00. Without days it applies every day.', 'sub' => ['from' => ['Comes on at', ''], 'to' => ['Goes off at', ''], 'days' => ['Days', 'Optional: empty means every day.']]],
            'heating_temp'   => ['Temperature (°C)', 'Optional. The one set, or the one you recommend. For example: 20.'],
            'heating_period' => ['Time of year', 'Optional. For example: from 15 October to 15 April.'],
            'heating_how'    => ['How to use it', 'Optional. One step per line: where the thermostat is, how to turn it up or down.'],
            'ac_mode'        => ['Air conditioning', '', 'options' => ['libero' => 'Guests turn it on whenever they like', 'orari' => 'It only works at certain times', 'no' => 'There is none']],
            'ac_times'       => ['When it works', 'One row per time slot. Without days it applies every day.', 'sub' => ['from' => ['From', ''], 'to' => ['To', ''], 'days' => ['Days', 'Optional: empty means every day.']]],
            'ac_temp'        => ['Recommended temperature (°C)', 'Optional. For example: 26.'],
            'ac_how'         => ['How to turn it on', 'Optional. One step per line: where the remote is, which button to press.'],
            'risparmio'      => ['Show the energy-saving tip', 'In the guide, two kind lines: energy is expensive in Italy, windows closed while the system is on, switch off when going out.'],
            'note'           => ['Note', 'Optional.'],
        ],
    ],
    'extras' => [
        'breve' => 'Transfers, breakfast, late check-out: services on request.',
        'intro' => 'The services you offer on top, usually for a fee: transfers, breakfast, late check-out. In the guide every service has a "Request on WhatsApp" button: for it to appear, Settings needs a contact who answers on WhatsApp.',
        'fields' => [
            'items' => ['Extra services', 'One row per service: title, price, two lines of description, a photo.', 'sub' => ['title' => ['Title', 'For example: Transfer from the station.'], 'amount' => ['Price', 'Optional.'], 'unit' => ['Price unit', ''], 'description' => ['Description', 'Optional.'], 'price_note' => ['Note on the price', 'Optional. For example: free under 3 years old.'], 'photo' => ['Photo', 'Optional.']]],
            'note'  => ['Note', 'Optional. For example: to be requested one day in advance.'],
        ],
    ],
    'rules' => [
        'breve' => 'Smoking, pets, parties, quiet hours.',
        'intro' => 'The house rules, said once and clearly: they avoid misunderstandings during the stay. Pick the main ones among those ready (they translate themselves in the guide) and add your own.',
        'fields' => [
            'flags'      => ['The main rules', 'Choose the sentence guests will read in the guide. With "Not specified" the rule does not appear.', 'options' => ['smoking' => 'Smoking', 'pets' => 'Pets', 'parties' => 'Parties', 'visitors' => 'Outside visitors']],
            'quiet_on'   => ['Set quiet hours', 'For example from 22:00 to 08:00. If you switch it off, it does not appear in the guide.'],
            'quiet_from' => ['From', ''],
            'quiet_to'   => ['To', ''],
            'items'      => ['Additional rules', 'One rule per line.'],
            'note'       => ['Note', 'Optional.'],
        ],
    ],
    'arrival' => [
        'breve' => 'Address, map and directions for every means of transport.',
        'intro' => 'The journey to the door: where guests come from (motorway, station, airport) and how they reach the property. Getting around during the stay goes in "Getting around".',
        'fields' => [
            'address'  => ['Address', ''],
            'maps_url' => ['Google Maps link', 'Optional. Without it, the map opens on the address.'],
            'routes'   => ['Directions by means of transport', 'One row per means: by car, by train, by plane, by bus. Guests only read the one they need.', 'sub' => ['mode' => ['Means of transport', ''], 'steps' => ['Steps', 'One step per line.']]],
            'note'     => ['Note', 'Optional.'],
        ],
    ],
    'transport' => [
        'breve' => 'Buses, taxis, rentals and shuttles during the stay.',
        'intro' => 'How to get around during the stay: buses, taxis, rentals, shuttles, escalators. Directions to reach the property go in "How to get here".',
        'fields' => [
            'options' => ['Getting around', 'One row for each way of getting around: the bus line, the taxi, who rents bikes.', 'sub' => ['type' => ['Type', ''], 'name' => ['Name', 'For example: the line to the centre.'], 'phone' => ['Phone', 'Optional.'], 'url' => ['Website', 'Optional.'], 'where' => ['Where to catch it', 'Optional. For example: the stop in front of the pharmacy.'], 'note' => ['Times, tickets, costs', 'Optional.']]],
            'note'    => ['Note', 'Optional.'],
        ],
    ],
    'parking' => [
        'breve' => 'Where to leave the car, how much it costs, the restricted traffic zone.',
        'intro' => 'Where to leave the car: one row for each option, with the cost and the minutes on foot from the property. If there is a restricted traffic zone (ZTL), write hours and gates: you spare your guests a fine.',
        'fields' => [
            'options' => ['Where to park', 'One row for each option: private space, public car park, garage…', 'sub' => ['type' => ['Type', ''], 'name' => ['Name or description', 'For example: reserved space in the courtyard.'], 'address' => ['Address', ''], 'maps_url' => ['Google Maps link', 'Optional.'], 'cost_hour' => ['Cost per hour', ''], 'cost_day' => ['Cost per day', ''], 'cost_note' => ['Note on the cost', 'Optional. For example: free on Sundays.'], 'walk_minutes' => ['Minutes on foot', 'From the property.'], 'instructions' => ['Instructions', 'Optional. For example: the gate opens with the remote you find at the entrance.'], 'photo' => ['Photo', 'Optional.']]],
            'ztl'     => ['Restricted traffic zone (ZTL)', 'Optional. Hours and gates of the restricted traffic zone.'],
        ],
    ],
    'waste' => [
        'breve' => 'Collection days, bin colours, where they are.',
        'intro' => 'How recycling works at your place: what goes where, on which days and in which bin. Guests from elsewhere do not know your municipality\'s rules: here they find them ready.',
        'fields' => [
            'bins' => ['Recycling', 'One row per type of waste: collection days, bin colour, where it is.', 'sub' => ['type' => ['Type', ''], 'label' => ['Description', 'Optional. For example: cans together with plastic.'], 'color' => ['Bin colour', ''], 'where' => ['Where it is', 'Optional.'], 'days' => ['Collection days', '']]],
            'note' => ['Note', 'Optional. For example: where the building\'s bins are.'],
        ],
    ],
    'eat' => [
        'breve' => 'Restaurants, bars and ice-cream shops you recommend.',
        'intro' => 'The places you send your guests to eat and drink. For each one a Google Maps link and two lines on why you like it are enough: the tips an online search does not give.',
        'fields' => ['intro' => ['Introduction', 'Optional. A sentence that introduces your tips.'], 'host_note' => ['Your personal tip', 'Optional. In the guide it appears signed with your name.']],
    ],
    'visit' => [
        'breve' => 'Monuments, villages, museums and viewpoints.',
        'intro' => 'The places to see: monuments, villages, museums, viewpoints. For each one a Google Maps link and two lines on why it is worth the visit are enough. Activities (hikes, tastings, spas) go in "Things to do".',
        'fields' => ['intro' => ['Introduction', 'Optional. A sentence that introduces your tips.'], 'host_note' => ['Your personal tip', 'Optional. In the guide it appears signed with your name.']],
    ],
    'todo' => [
        'breve' => 'Hikes, tastings, spas, activities.',
        'intro' => 'The experiences to have in the area: hikes, tastings, spas, activities for children. For each one a Google Maps link and two lines on why you recommend it are enough. Places to visit go in "What we recommend seeing".',
        'fields' => ['intro' => ['Introduction', 'Optional. A sentence that introduces your tips.'], 'host_note' => ['Your personal tip', 'Optional. In the guide it appears signed with your name.']],
    ],
    'shop' => [
        'breve' => 'Grocery, bakery, market, pharmacy, cash machine.',
        'intro' => 'Where to shop and find what is needed every day: grocery, bakery, market, pharmacy, cash machine. With a label you flag who is open on Sundays or until late.',
        'fields' => ['intro' => ['Introduction', 'Optional. A sentence that introduces your tips.'], 'host_note' => ['Your personal tip', 'Optional. In the guide it appears signed with your name.']],
    ],
    'emergency' => [
        'breve' => 'Useful numbers, called with one tap.',
        'intro' => 'The numbers to have at hand if something goes wrong: guests call them with one tap. Start from 112 and add the out-of-hours doctor, the pharmacy and your number for emergencies.',
        'fields' => [
            'emergency_number' => ['Single emergency number', 'In Italy it is 112.'],
            'contacts'         => ['Useful contacts', 'One row per contact: out-of-hours doctor, on-duty pharmacy, your number for emergencies.', 'sub' => ['name' => ['Name', ''], 'phone' => ['Phone', ''], 'note' => ['Note', 'Optional. Hours, address…']]],
            'note'             => ['Note', 'Optional.'],
        ],
    ],
    'events' => [
        'breve' => 'Festivals, markets and concerts, with the dates.',
        'intro' => 'What is going on in the area: festivals, markets, concerts, with the date and the poster. Past events disappear from the guide by themselves and stay here until you remove them; those that come back every year you repeat with one click.',
        'fields' => [
            'intro'  => ['Introduction', 'Optional.'],
            'events' => ['Events', 'One row per event: festivals, markets, concerts. With the poster, if there is one.', 'sub' => ['name' => ['Name', ''], 'cat' => ['Category', ''], 'when' => ['When', ''], 'date_from' => ['From', ''], 'date_to' => ['To', ''], 'time_from' => ['From (time)', ''], 'time_to' => ['To (time)', 'Optional.'], 'days' => ['Days', ''], 'when_text' => ['When, in words', 'For example: the second Sunday of the month.'], 'yearly' => ['Repeats every year in the same period', ''], 'place' => ['Place', ''], 'dist_min' => ['Distance (min)', ''], 'dist_mode' => ['Means of transport', ''], 'price_kind' => ['Admission', ''], 'price' => ['How much it costs', 'For example: €5.'], 'url' => ['Website or tickets', 'Optional.'], 'description' => ['Description', 'Optional.'], 'poster' => ['Poster', 'An image or a PDF.'], 'poster_pdf' => ['Poster as PDF', ''], 'recommended' => ['I recommend it', '']]],
        ],
    ],
    'custom' => [
        'nome'  => 'Free section',
        'breve' => 'Title, icon and text are up to you.',
        'intro' => 'For what does not fit in the other sections: the pool, the garden, the history of the place. Choose the title and the icon: one per guide, in every plan (with Essential it counts as one of the 6 sections).',
        'fields' => [
            'icona' => ['Icon', 'It appears in the guide, on the section tile.', 'options' => ['star' => 'Star', 'info' => 'Information', 'book' => 'Book', 'key' => 'Key', 'sun' => 'Sun', 'moon' => 'Moon', 'coffee' => 'Coffee', 'grill' => 'Barbecue', 'paw' => 'Pets', 'music' => 'Music', 'people' => 'People', 'compass' => 'Compass']],
            'text'  => ['Text', 'What you want to tell.'],
            'items' => ['List', 'Optional. One item per line.'],
        ],
    ],
    'info' => [
        'breve' => 'Practical notices that fit nowhere else.',
        'intro' => 'The practical things worth knowing that do not fit in the other sections: tap water, market day, the meter that trips. One item per line, short.',
        'fields' => ['items' => ['Things to know', 'One item per line.'], 'note' => ['Note', 'Optional.']],
    ],
];

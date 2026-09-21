/**
 * Indian States & Cities Helper
 * Requirement: State dropdown must display Gujarat, Rajasthan, Maharashtra at the top,
 * followed by all other Indian states and union territories.
 */

const INDIAN_STATES = [
    // Top Priority States
    "Gujarat",
    "Rajasthan",
    "Maharashtra",

    // Other States & UTs (Alphabetical)
    "Andhra Pradesh",
    "Arunachal Pradesh",
    "Assam",
    "Bihar",
    "Chandigarh",
    "Chhattisgarh",
    "Dadra and Nagar Haveli and Daman and Diu",
    "Delhi",
    "Goa",
    "Haryana",
    "Himachal Pradesh",
    "Jammu and Kashmir",
    "Jharkhand",
    "Karnataka",
    "Kerala",
    "Ladakh",
    "Lakshadweep",
    "Madhya Pradesh",
    "Manipur",
    "Meghalaya",
    "Mizoram",
    "Nagaland",
    "Odisha",
    "Puducherry",
    "Punjab",
    "Sikkim",
    "Tamil Nadu",
    "Telangana",
    "Tripura",
    "Uttar Pradesh",
    "Uttarakhand",
    "West Bengal",
    "Andaman and Nicobar Islands"
];

const INDIAN_CITIES = {
    "Gujarat": [
        "Ahmedabad", "Surat", "Vadodara", "Rajkot", "Bhavnagar", "Jamnagar", "Junagadh", "Gandhinagar", 
        "Gandhidham", "Anand", "Navsari", "Morbi", "Nadiad", "Surendranagar", "Bharuch", "Mehsana", 
        "Bhuj", "Porbandar", "Palanpur", "Valsad", "Vapi", "Gondal", "Veraval", "Godhra", "Patan", 
        "Kalol", "Dahod", "Botad", "Amreli", "Deesa", "Jetpur"
    ],
    "Rajasthan": [
        "Jaipur", "Jodhpur", "Kota", "Bikaner", "Ajmer", "Udaipur", "Bhilwara", "Alwar", "Bharatpur", 
        "Sikar", "Pali", "Sri Ganganagar", "Beawar", "Barmer", "Hanumangarh", "Kishangarh", "Tonk", 
        "Jhunjhunu", "Churu", "Chittorgarh", "Sawai Madhopur", "Bundi", "Nagaur", "Dausa", "Makrana", 
        "Sujangarh", "Jhalawar", "Hindaun", "Gangapur City", "Banswara", "Mount Abu", "Jaisalmer"
    ],
    "Maharashtra": [
        "Mumbai", "Pune", "Nagpur", "Thane", "Pimpri-Chinchwad", "Nashik", "Kalyan-Dombivli", "Vasai-Virar", 
        "Aurangabad (Chhatrapati Sambhaji Nagar)", "Navi Mumbai", "Solapur", "Mira-Bhayandar", "Bhiwandi", 
        "Amravati", "Nanded", "Kolhapur", "Ulhasnagar", "Sangli-Miraj-Kupwad", "Malegaon", "Jalgaon", 
        "Akola", "Latur", "Dhule", "Ahmednagar", "Chandrapur", "Parbhani", "Ichalkaranji", "Jalna", 
        "Panvel", "Satara", "Beed", "Yavatmal", "Wardha", "Barshi", "Gondia"
    ],
    "Delhi": ["New Delhi", "North Delhi", "South Delhi", "East Delhi", "West Delhi", "Central Delhi"],
    "Madhya Pradesh": ["Indore", "Bhopal", "Jabalpur", "Gwalior", "Ujjain", "Sagar", "Dewas", "Satna", "Ratlam", "Rewa", "Singrauli", "Katni"],
    "Uttar Pradesh": ["Lucknow", "Kanpur", "Noida", "Ghaziabad", "Varanasi", "Agra", "Prayagraj", "Meerut", "Bareilly", "Aligarh", "Moradabad", "Gorakhpur", "Saharanpur"],
    "Haryana": ["Gurugram", "Faridabad", "Panipat", "Ambala", "Karnal", "Hisar", "Rohtak", "Sonipat", "Panchkula", "Yamunanagar"],
    "Punjab": ["Ludhiana", "Amritsar", "Jalandhar", "Patiala", "Bathinda", "Mohali", "Hoshiarpur", "Pathankot", "Moga"],
    "Karnataka": ["Bengaluru", "Mysuru", "Hubballi-Dharwad", "Mangaluru", "Belagavi", "Kalaburagi", "Davanagere", "Ballari", "Vijayapura", "Shivamogga"],
    "Tamil Nadu": ["Chennai", "Coimbatore", "Madurai", "Tiruchirappalli", "Salem", "Tiruppur", "Erode", "Vellore", "Thoothukudi", "Dindigul"],
    "Telangana": ["Hyderabad", "Warangal", "Nizamabad", "Khammam", "Karimnagar", "Ramagundam", "Mahbubnagar", "Nalgonda"],
    "Andhra Pradesh": ["Visakhapatnam", "Vijayawada", "Guntur", "Nellore", "Kurnool", "Rajahmundry", "Tirupati", "Kadapa", "Kakinada", "Anantapur"],
    "West Bengal": ["Kolkata", "Howrah", "Asansol", "Siliguri", "Durgapur", "Bardhaman", "Kharagpur", "Haldia"],
    "Bihar": ["Patna", "Gaya", "Bhagalpur", "Muzaffarpur", "Purnia", "Darbhanga", "Bihar Sharif", "Arrah", "Begusarai", "Katihar"],
    "Goa": ["Panaji", "Margao", "Vasco da Gama", "Mapusa", "Ponda"],
    "Chandigarh": ["Chandigarh"],
    "Chhattisgarh": ["Raipur", "Bhilai", "Bilaspur", "Korba", "Rajnandgaon", "Durg"],
    "Jharkhand": ["Ranchi", "Jamshedpur", "Dhanbad", "Bokaro Steel City", "Deoghar", "Hazaribagh"],
    "Odisha": ["Bhubaneswar", "Cuttack", "Rourkela", "Berhampur", "Sambalpur", "Puri", "Balasore"],
    "Himachal Pradesh": ["Shimla", "Dharamshala", "Mandi", "Solan", "Kullu", "Manali", "Baddi"],
    "Jammu and Kashmir": ["Srinagar", "Jammu", "Anantnag", "Baramulla", "Udhampur"],
    "Uttarakhand": ["Dehradun", "Haridwar", "Roorkee", "Haldwani", "Rishikesh", "Kashipur", "Rudrapur"],
    "Assam": ["Guwahati", "Silchar", "Dibrugarh", "Jorhat", "Nagaon", "Tinsukia", "Tezpur"],
    "Kerala": ["Thiruvananthapuram", "Kochi", "Kozhikode", "Thrissur", "Kollam", "Palakkad", "Alappuzha", "Kannur"]
};

/**
 * Attaches dynamic Indian State and City dropdown behavior
 * @param {string|jQuery} stateElem - Selector or jQuery object for State element
 * @param {string|jQuery} cityElem - Selector or jQuery object for City element
 * @param {string} initialSelectedState - Preselected state value
 * @param {string} initialSelectedCity - Preselected city value
 */
function initIndianStateCity(stateElem, cityElem, initialSelectedState, initialSelectedCity) {
    const $state = $(stateElem);
    const $city = $(cityElem);

    if (!$state.length) return;

    let selectedState = (initialSelectedState || $state.val() || '').trim();
    let selectedCity = (initialSelectedCity || $city.val() || '').trim();

    // If $state is already a select or needs to become one
    if ($state.is('select')) {
        $state.empty();
        $state.append('<option value="">-- Select State --</option>');
        
        let topGroup = $('<optgroup label="⭐ Top States"></optgroup>');
        let otherGroup = $('<optgroup label="Other States & UTs"></optgroup>');

        INDIAN_STATES.forEach(function (state, index) {
            let $opt = $('<option></option>').val(state).text(state);
            if (index < 3) {
                topGroup.append($opt);
            } else {
                otherGroup.append($opt);
            }
        });

        $state.append(topGroup);
        $state.append(otherGroup);

        if (selectedState) {
            $state.val(selectedState);
        }
    }

    function populateCities(stateName, keepCityVal) {
        if (!$city.length) return;

        let cities = INDIAN_CITIES[stateName] || [];
        let currentCity = keepCityVal || $city.val() || selectedCity || '';

        if ($city.is('select')) {
            $city.empty();
            $city.append('<option value="">-- Select City --</option>');

            let cityFound = false;
            cities.forEach(function (c) {
                let $cOpt = $('<option></option>').val(c).text(c);
                if (c.toLowerCase() === currentCity.toLowerCase()) {
                    $cOpt.prop('selected', true);
                    cityFound = true;
                }
                $city.append($cOpt);
            });

            // If an existing city value isn't in standard list, append it as custom
            if (currentCity && !cityFound) {
                $city.append($('<option selected></option>').val(currentCity).text(currentCity + ' (Other)'));
            }

            // Enable select2 tagging if available so user can type any city
            if ($.fn.select2 && !$city.hasClass('select2-hidden-accessible')) {
                $city.select2({
                    tags: true,
                    placeholder: 'Select or type city',
                    allowClear: true,
                    width: '100%'
                });
            } else if ($.fn.select2 && $city.hasClass('select2-hidden-accessible')) {
                $city.trigger('change.select2');
            }
        }
    }

    $state.on('change', function () {
        let stateVal = $(this).val();
        populateCities(stateVal, '');
    });

    if (selectedState) {
        populateCities(selectedState, selectedCity);
    }
}

// Global exposure
window.INDIAN_STATES = INDIAN_STATES;
window.INDIAN_CITIES = INDIAN_CITIES;
window.initIndianStateCity = initIndianStateCity;

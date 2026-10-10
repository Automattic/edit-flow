const filters = [
    {
        name: 'post_status',
        filterType: 'select',
        label: 'Select a status',
        options: [
            {
                value: '',
                label: 'Select a status'
            },
            {
                value: 'publish',
                label: 'Published'
            },
            {
                value: 'future',
                label: 'Scheduled'
            },
            {
                value: 'pitch',
                label: 'Pitch'
            },
            {
                value: 'assigned',
                label: 'Assigned'
            },
            {
                value: 'in-progress',
                label: 'In Progress'
            },
            {
                value: 'draft',
                label: 'Draft'
            },
            {
                value: 'pending',
                label: 'Pending Review'
            }
        ],
        initialValue: ''
    },
    {
        name: 'author',
        filterType: 'combobox',
        inputLabel: 'Find a user',
        buttonOpenLabel: 'Open user menu',
        buttonCloseLabel: 'Close user menu',
        buttonClearLabel: 'Clear user selection',
        placeholder: 'Select a user',
        options: [
            {
                value: 1,
                name: 'admin'
            }
        ],
        initialValue: null
    },
    {
        name: 'cat',
        filterType: 'combobox',
        inputLabel: 'Find a category',
        buttonOpenLabel: 'Open category menu',
        buttonCloseLabel: 'Close category menu',
        buttonClearLabel: 'Clear category selection',
        placeholder: 'Select a category',
        options: [
            {
                value: 1,
                name: 'Uncategorized',
                parent: 0,
                level: 0
            }
        ],
        initialValue: null
    },
    {
        name: 'cpt',
        filterType: 'select',
        label: 'Select a type',
        options: [
            {
                value: '',
                label: 'Select a type'
            },
            {
                value: 'post',
                label: 'Posts'
            },
            {
                value: 'page',
                label: 'Pages'
            }
        ],
        initialValue: ''
    },
    {
        name: 'num_weeks',
        filterType: 'select',
        label: 'Number of weeks',
        options: [
            {
                value: 1,
                label: '1 week'
            },
            {
                value: 2,
                label: '2 weeks'
            },
            {
                value: 3,
                label: '3 weeks'
            },
            {
                value: 4,
                label: '4 weeks'
            },
            {
                value: 5,
                label: '5 weeks'
            },
            {
                value: 6,
                label: '6 weeks'
            },
            {
                value: 7,
                label: '7 weeks'
            },
            {
                value: 8,
                label: '8 weeks'
            },
            {
                value: 9,
                label: '9 weeks'
            },
            {
                value: 10,
                label: '10 weeks'
            },
            {
                value: 11,
                label: '11 weeks'
            },
            {
                value: 12,
                label: '12 weeks'
            }
        ],
        initialValue: 6
    }
];

const filterValues = {
    '0': '',
    post_status: '',
    cpt: '',
    cat: 0,
    author: 0,
    num_weeks: 6,
    start_date: '2020-04-13'
};

const numberOfWeeks = 6;

const beginningOfWeek = "2020-04-13";

const pageUrl = "http://wordpress.test/wp-admin/index.php?page=calendar";

export { filters, filterValues, numberOfWeeks, beginningOfWeek, pageUrl };
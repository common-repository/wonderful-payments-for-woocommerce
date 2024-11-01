// Import necessary modules from wp.element
const { useState, useEffect } = wp.element;
const { registerPaymentMethod } = window.wc.wcBlocksRegistry;

// Define the settings and label for the Wonderful Payments Gateway
const settings = window.wc.wcSettings.getSetting('wonderful_payments_gateway_data', {});

const PaymentLabel = ({ text, iconUrl, linkUrl }) => {
    return window.wp.element.createElement(
        'div',
        {
            style: {
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'center',
                width: '100%'
            }
        },
        window.wp.element.createElement(
            'span',
            null,
            text
        ),
        window.wp.element.createElement(
            'a',
            {
                href: linkUrl,
                target: '_blank',
                rel: 'noopener noreferrer',
                style: {
                    display: 'flex',
                    alignItems: 'center',
                    textDecoration: 'none',  // Remove underline
                    border: '2px solid #4556a5',  // Blue border
                    padding: '5px 10px',
                    borderRadius: '5px',  // Rounded corners
                    color: '#4556a5',  // Text color
                    cursor: 'pointer'
                }
            },
            window.wp.element.createElement(
                'span',
                {
                    style: {
                        marginRight: '2px'
                    }
                },
                'What is'
            ),
            iconUrl && window.wp.element.createElement(
                'img',
                {
                    src: iconUrl,
                    alt: 'Payment Icon',
                    style: {
                        width: '60px',
                        height: '60px',
                        marginBottom: '8px'
                    }
                }
            ),
            window.wp.element.createElement(
                'span',
                null,
                '?'
            )
        )
    );
};

const labelComponent = window.wp.element.createElement(PaymentLabel, {
    text: settings.title || window.wp.i18n.__('Wonderful Payments Gateway', 'wonderful_payments_gateway'),
    iconUrl: settings.icon,
    linkUrl: 'https://wonderful.co.uk'
});

// Define the Icon component
const Icon = () => {
    if (settings.icon) {
        const img = document.createElement('img');
        img.src = settings.icon;
        img.style.float = 'right';
        img.style.marginRight = '20px';
        return img;
    }
    return null;
};

// Define the BankList component
const BankList = ({ banks, eventRegistration, emitResponse }) => {
    const [searchTerm, setSearchTerm] = useState('');
    const [selectedBank, setSelectedBank] = useState(null);
    const [lastClickedButton, setLastClickedButton] = useState(null);

    // Function to handle payment setup
    useEffect(() => {
        const unsubscribe = eventRegistration.onPaymentSetup(async () => {
            const customDataIsValid = !!selectedBank; // Check if a bank is selected

            if (customDataIsValid) {
                return {
                    type: emitResponse.responseTypes.SUCCESS,
                    meta: {
                        paymentMethodData: {
                            aspsp_name: selectedBank,
                        },
                    },
                };
            }

            return {
                type: emitResponse.responseTypes.ERROR,
                message: 'No bank selected',
            };
        });

        // Unsubscribes when this component is unmounted.
        return () => {
            unsubscribe();
        };
    }, [selectedBank, eventRegistration.onPaymentSetup, emitResponse.responseTypes.ERROR, emitResponse.responseTypes.SUCCESS]);

    if (!banks) {
        return null;
    }

    // Filter the banks based on the search term
    const filteredBanks = banks.filter(bank => bank.bank_name.toLowerCase().includes(searchTerm.toLowerCase()));

    // Create an array of button elements
    const buttons = filteredBanks.map((bank, index) => {

        // Create a logo element
        const logo = window.wp.element.createElement('img', {
            src: bank.bank_logo,
            style: {
                height: '3rem',
                width: '3rem',
                marginRight: '1rem'
            }
        });

        let issueIcon = null;
        let offlineIcon = null;

        if (bank.status === 'issues') {
            issueIcon = window.wp.element.createElement('i', {
                className: 'fas fa-exclamation-triangle',
                style: {
                    color: '#FFC107'
                },
                'data-toggle': 'tooltip',
                'data-placement': 'top',
                title: 'This bank may be experiencing issues'
            });
        }

        if (bank.status === 'offline') {
            offlineIcon = window.wp.element.createElement('i', {
                className: 'fas fa-exclamation-square',
                style: {
                    color: '#A0AEC0'
                },
                'data-toggle': 'tooltip',
                'data-placement': 'top',
                title: 'This bank is currently offline'
            });
        }

        return window.wp.element.createElement(
            'div',
            {
                key: index,
                style: {
                    width: '90%',
                    border: '1px solid #E2E8F0',
                    transition: 'box-shadow 0.15s ease-in-out, border-color 0.15s ease-in-out',
                    padding: '0 1rem',
                    display: 'flex',
                    alignItems: 'center',
                    margin: '5px auto',
                    cursor: 'pointer'
                },
                onMouseOver: (event) => {
                    event.currentTarget.style.boxShadow = '0 4px 6px rgba(0, 0, 0, 0.1)';
                    event.currentTarget.style.borderColor = '#4299e1';
                },
                onMouseOut: (event) => {
                    event.currentTarget.style.boxShadow = 'none';
                    event.currentTarget.style.borderColor = '#E2E8F0';
                },
                onClick: (event) => {
                    // If there was a previously clicked button, reset its styles
                    if (lastClickedButton) {
                        lastClickedButton.style.backgroundColor = '';
                        lastClickedButton.style.color = '#000000';
                    }

                    // Apply the styles to the newly clicked button
                    event.currentTarget.style.backgroundColor = '#1F2A64';
                    event.currentTarget.style.color = '#ffffff';

                    // Update the last clicked button
                    setLastClickedButton(event.currentTarget);

                    // Set the selected bank
                    setSelectedBank(bank.bank_id);
                }
            },
            logo,
            bank.bank_name,
            issueIcon,
            offlineIcon
        );
    });

    // Create a div element and append the divs to it
    const bankListDiv = window.wp.element.createElement(
        'div',
        null,
        buttons
    );

    // Create a logo element
    const logo = window.wp.element.createElement('img', {
        src: 'https://wonderful.one/images/logo.png',
        style: {
            display: 'block',
            marginLeft: 'auto',
            marginRight: 'auto',
            marginTop: '25px',
            width: '25%'
        }
    });

    // Create a strap line element
    const strapLine = window.wp.element.createElement('p', {
        style: {
            textAlign: 'center',
            fontSize: '0.8em'
        }
    }, 'Simple, fast and secure instant bank payments.');

    // Create the SVG path element
    const svgPath = window.wp.element.createElement('path', {
        fillRule: 'evenodd',
        d: 'M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z',
        clipRule: 'evenodd'
    });

    // Create the SVG element
    const svg = window.wp.element.createElement('svg', {
        className: 'pointer-events-none absolute inset-y-0 left-0 h-5 w-5 text-gray-400 ml-3 my-auto', // Adjust the size and position
        viewBox: '0 0 20 20',
        fill: '#808080',
        'aria-hidden': 'true',
        style: {
            height: '20px',
            width: '20px'
        }
    }, svgPath);

    // Create a search box
    const searchBox = window.wp.element.createElement('input', {
        type: 'text',
        placeholder: 'Search for a bank...',
        style: {
            width: 'calc(100% - 2rem)',
            padding: '0.5rem',
            margin: '5px auto',
            display: 'block',
            border: 'none',
            outline: 'none',
            fontSize: '1em',
            backgroundColor: 'transparent'
        },
        value: searchTerm,
        onChange: (event) => {
            setSearchTerm(event.target.value);

            // If there was a previously clicked button, reset its styles
            if (lastClickedButton) {
                lastClickedButton.style.backgroundColor = '';
                lastClickedButton.style.color = '#000000';
            }
        }
    });

    // Create a div to hold the search box and the SVG
    const searchDiv = window.wp.element.createElement('div', {
        style: {
            position: 'relative',
            width: '92%',
            padding: '0.5rem',
            margin: '5px auto',
            display: 'flex',
            alignItems: 'center',
            border: '1px solid #E2E8F0',
            borderRadius: '0.25rem'
        }
    }, svg, searchBox);

    // Create a suffix element
    const suffix = window.wp.element.createElement('p', {
        style: {
            fontSize: '0.7em',
            textAlign: 'center',
            marginTop: '25px',
        },
        dangerouslySetInnerHTML: {
            __html: 'Instant payments are processed by <a href="https://wonderful.co.uk" target="_blank">Wonderful Payments</a> and are subject to their <a href="https://wonderful.co.uk/legal" target="_blank">Consumer Terms and Privacy Policy</a>.'
        }
    });

    const innerDiv = window.wp.element.createElement('div', {
        style: {
            backgroundColor: 'white',
            height: '28rem',
            overflowY: 'auto'
        }
    }, logo, strapLine, searchDiv, bankListDiv, suffix);

    const outerDiv = window.wp.element.createElement('div', {
        style: {
            marginLeft: 'auto',
            marginRight: 'auto',
            display: 'grid',
            gridTemplateColumns: 'repeat(1, minmax(0, 1fr))',
            alignItems: 'start',
            gap: '1rem',
            marginTop: '1rem'
        }
    }, innerDiv);

    return outerDiv;
};

// Define the Block_Gateway object
const Block_Gateway = {
    name: 'wonderful_payments_gateway',
    label: labelComponent,
    icon: Object(window.wp.element.createElement)(Icon, null),
    content: Object(window.wp.element.createElement)(BankList, { banks: settings.banks }),
    edit: Object(window.wp.element.createElement)(BankList, { banks: settings.banks }),
    canMakePayment: () => true,
    ariaLabel: settings.title || window.wp.i18n.__('Wonderful Payments Gateway', 'wonderful_payments_gateway'),
    supports: {
        features: settings.supports,
    },
};

// Register the payment method
window.wc.wcBlocksRegistry.registerPaymentMethod(Block_Gateway);

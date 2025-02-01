/**
 * Template Editor Alpine.js Component
 * Manages the dynamic form for editing report template sections
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('templateEditor', (data) => ({
        sections: {},
        chartSections: {},
        errors: [],

        init() {
            // Initialize sections with default structure
            const defaultSections = {
                'spiritual': {
                    title: 'Spiritual',
                    items: []
                },
                'emotional': {
                    title: 'Social Emotional',
                    items: []
                },
                'physical': {
                    title: 'Physical',
                    items: []
                },
                'mental': {
                    title: 'Mental',
                    items: []
                }
            };

            // Initialize chart sections with default structure
            const defaultChartSections = {
                'spiritual (chart)': {
                    title: 'Spiritual Development',
                    subsections: {}
                },
                'emotional (chart)': {
                    title: 'Social Emotional Development',
                    subsections: {}
                },
                'physical (chart)': {
                    title: 'Physical Development',
                    subsections: {}
                },
                'mental (chart)': {
                    title: 'Mental Development',
                    subsections: {}
                }
            };

            // Initialize with data or defaults
            if (data?.sections) {
                // Handle legacy data structure
                if (Array.isArray(data.sections)) {
                    const convertedSections = {};
                    data.sections.forEach(section => {
                        const type = section.type === 'social_emotional' ? 'emotional' : section.type;
                        if (type) {
                            convertedSections[type] = {
                                title: section.title || defaultSections[type].title,
                                items: Array.isArray(section.items) ? section.items : Object.values(section.items || {})
                            };
                        }
                    });
                    this.sections = { ...defaultSections, ...convertedSections };
                } else {
                    // Handle object structure
                    const convertedSections = {};
                    Object.entries(data.sections).forEach(([type, section]) => {
                        const newType = type === 'social_emotional' ? 'emotional' : type;
                        convertedSections[newType] = {
                            title: section.title || defaultSections[newType]?.title,
                            items: Array.isArray(section.items) ? section.items : []
                        };
                    });
                    this.sections = { ...defaultSections, ...convertedSections };
                }
            } else {
                this.sections = defaultSections;
            }

            // Initialize chart sections
            if (data?.chartSections) {
                // Handle legacy data structure
                if (Array.isArray(data.chartSections)) {
                    const convertedChartSections = {};
                    data.chartSections.forEach(section => {
                        const type = section.type === 'social_emotional' ? 'emotional' : section.type;
                        if (type) {
                            const subsections = {};
                            if (section.subsections) {
                                if (Array.isArray(section.subsections)) {
                                    section.subsections.forEach(sub => {
                                        if (typeof sub === 'string') {
                                            subsections[sub] = sub;
                                        } else if (sub.name) {
                                            subsections[sub.name] = sub.name;
                                        }
                                    });
                                } else {
                                    Object.entries(section.subsections).forEach(([key, value]) => {
                                        subsections[key] = value;
                                    });
                                }
                            }
                            convertedChartSections[`${type} (chart)`] = {
                                title: section.title || defaultChartSections[`${type} (chart)`].title,
                                subsections: subsections
                            };
                        }
                    });
                    this.chartSections = { ...defaultChartSections, ...convertedChartSections };
                } else {
                    // Handle object structure
                    const convertedChartSections = {};
                    Object.entries(data.chartSections).forEach(([type, section]) => {
                        const newType = type.replace('social_emotional', 'emotional');
                        convertedChartSections[newType] = {
                            title: section.title || defaultChartSections[newType]?.title,
                            subsections: section.subsections || {}
                        };
                    });
                    this.chartSections = { ...defaultChartSections, ...convertedChartSections };
                }
            } else {
                this.chartSections = defaultChartSections;
            }
        },

        // Item Management for Assessment Sections
        addItem(sectionType) {
            if (!this.sections[sectionType]) {
                this.sections[sectionType] = { 
                    title: sectionType === 'emotional' ? 'Social Emotional' : sectionType.charAt(0).toUpperCase() + sectionType.slice(1),
                    items: []
                };
            }
            this.sections[sectionType].items.push('');
        },

        removeItem(sectionType, index) {
            if (this.sections[sectionType] && Array.isArray(this.sections[sectionType].items)) {
                this.sections[sectionType].items.splice(index, 1);
            }
        },

        // Subsection Management for Development Chart
        addSubsection(sectionType) {
            if (!this.chartSections[sectionType]) {
                this.chartSections[sectionType] = { 
                    title: sectionType === 'emotional (chart)' ? 'Social Emotional Development' : 
                          sectionType.replace(' (chart)', '').charAt(0).toUpperCase() + 
                          sectionType.replace(' (chart)', '').slice(1) + ' Development',
                    subsections: {}
                };
            }
            const timestamp = Date.now().toString();
            this.chartSections[sectionType].subsections[timestamp] = '';
        },

        removeSubsection(sectionType, key) {
            if (this.chartSections[sectionType]?.subsections) {
                const { [key]: removed, ...rest } = this.chartSections[sectionType].subsections;
                this.chartSections[sectionType].subsections = rest;
            }
        },

        // Form Submission
        getFormData() {
            // Convert sections to server format
            const convertedSections = {};
            Object.entries(this.sections).forEach(([type, section]) => {
                const serverType = type === 'emotional' ? 'social_emotional' : type;
                convertedSections[serverType] = {
                    type: serverType,
                    title: section.title,
                    items: section.items.filter(item => item.trim() !== '')
                };
            });

            // Convert chart sections to server format
            const convertedChartSections = {};
            Object.entries(this.chartSections).forEach(([type, section]) => {
                const serverType = type.replace(' (chart)', '');
                const finalType = serverType === 'emotional' ? 'social_emotional' : serverType;
                convertedChartSections[finalType] = {
                    type: finalType,
                    title: section.title,
                    subsections: Object.entries(section.subsections).reduce((acc, [key, value]) => {
                        acc[key] = value;
                        return acc;
                    }, {})
                };
            });

            return {
                sections: convertedSections,
                chartSections: convertedChartSections
            };
        },

        // Validation
        validate() {
            this.errors = [];
            
            // Validate sections
            Object.entries(this.sections).forEach(([type, section]) => {
                if (!section.title) {
                    this.errors.push(`Section ${type} must have a title`);
                }
            });

            // Validate chart sections
            Object.entries(this.chartSections).forEach(([type, section]) => {
                if (!section.title) {
                    this.errors.push(`Chart section ${type} must have a title`);
                }
            });

            return this.errors.length === 0;
        }
    }));
});

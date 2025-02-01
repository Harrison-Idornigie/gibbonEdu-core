/**
 * Template Editor Alpine.js Component
 * Manages the dynamic form for editing report template sections
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('templateEditor', (initialData = null) => ({
        sections: {},
        chartSections: {},

        init() {
            console.log('Initializing with data:', initialData);
            // Initialize with provided data or defaults
            if (initialData) {
                this.sections = initialData.sections || {
                    spiritual: { title: 'Spiritual', items: [] },
                    emotional: { title: 'Social Emotional', items: [] },
                    physical: { title: 'Physical', items: [] },
                    mental: { title: 'Mental', items: [] }
                };
                
                this.chartSections = initialData.chartSections || {
                    'spiritual (chart)': { title: 'Spiritual Development', subsections: {} },
                    'emotional (chart)': { title: 'Social Emotional Development', subsections: {} },
                    'physical (chart)': { title: 'Physical Development', subsections: {} },
                    'mental (chart)': { title: 'Mental Development', subsections: {} }
                };
            } else {
                // Set defaults if no data provided
                this.sections = {
                    spiritual: { title: 'Spiritual', items: [] },
                    emotional: { title: 'Social Emotional', items: [] },
                    physical: { title: 'Physical', items: [] },
                    mental: { title: 'Mental', items: [] }
                };
                
                this.chartSections = {
                    'spiritual (chart)': { title: 'Spiritual Development', subsections: {} },
                    'emotional (chart)': { title: 'Social Emotional Development', subsections: {} },
                    'physical (chart)': { title: 'Physical Development', subsections: {} },
                    'mental (chart)': { title: 'Mental Development', subsections: {} }
                };
            }
            console.log('Initialized with:', { sections: this.sections, chartSections: this.chartSections });
        },

        // Item Management for Assessment Sections
        addItem(sectionType) {
            console.log('Adding item to:', sectionType);
            if (!this.sections[sectionType]) {
                this.sections[sectionType] = { 
                    title: sectionType === 'emotional' ? 'Social Emotional' : sectionType.charAt(0).toUpperCase() + sectionType.slice(1),
                    items: []
                };
            }
            if (!Array.isArray(this.sections[sectionType].items)) {
                this.sections[sectionType].items = [];
            }
            this.sections[sectionType].items.push('');
            console.log('Updated sections:', this.sections);
        },

        removeItem(sectionType, index) {
            console.log('Removing item:', { sectionType, index });
            if (this.sections[sectionType] && Array.isArray(this.sections[sectionType].items)) {
                this.sections[sectionType].items.splice(index, 1);
                console.log('Updated sections:', this.sections);
            }
        },

        // Subsection Management for Development Chart
        addSubsection(sectionType) {
            console.log('Adding subsection to:', sectionType);
            if (!this.chartSections[sectionType]) {
                this.chartSections[sectionType] = { 
                    title: sectionType === 'emotional (chart)' ? 'Social Emotional Development' : 
                          sectionType.replace(' (chart)', '').charAt(0).toUpperCase() + 
                          sectionType.replace(' (chart)', '').slice(1) + ' Development',
                    subsections: {}
                };
            }
            if (!this.chartSections[sectionType].subsections) {
                this.chartSections[sectionType].subsections = {};
            }
            const timestamp = Date.now().toString();
            this.chartSections[sectionType].subsections[timestamp] = '';
            console.log('Updated chartSections:', this.chartSections);
        },

        removeSubsection(sectionType, key) {
            console.log('Removing subsection:', { sectionType, key });
            if (this.chartSections[sectionType]?.subsections) {
                const { [key]: removed, ...rest } = this.chartSections[sectionType].subsections;
                this.chartSections[sectionType].subsections = rest;
                console.log('Updated chartSections:', this.chartSections);
            }
        },

        // Form Submission
        getFormData() {
            // Convert sections to server format
            const convertedSections = {};
            Object.entries(this.sections).forEach(([type, section]) => {
                const serverType = type === 'emotional' ? 'social_emotional' : type;
                convertedSections[serverType] = {
                    title: section.title,
                    items: (section.items || []).filter(item => item.trim() !== '')
                };
            });

            // Convert chart sections to server format
            const convertedChartSections = {};
            Object.entries(this.chartSections).forEach(([type, section]) => {
                const baseType = type.replace(' (chart)', '');
                const serverType = baseType === 'emotional' ? 'social_emotional' : baseType;
                const chartType = `${serverType} (chart)`;
                convertedChartSections[chartType] = {
                    title: section.title,
                    subsections: Object.fromEntries(
                        Object.entries(section.subsections || {})
                            .filter(([_, value]) => value.trim() !== '')
                            .map(([key, value]) => [value, value]) // Use the value as both key and value
                    )
                };
            });

            const formData = {
                sections: convertedSections,
                chartSections: convertedChartSections
            };
            console.log('Form data:', formData);
            return formData;
        },

        // Validation
        validate() {
            // Ensure all required sections exist
            const requiredSections = ['spiritual', 'emotional', 'physical', 'mental'];
            const requiredChartSections = requiredSections.map(s => s + ' (chart)');

            let valid = true;

            requiredSections.forEach(section => {
                if (!this.sections[section] || !Array.isArray(this.sections[section].items)) {
                    console.error(`Missing or invalid section: ${section}`);
                    valid = false;
                }
            });

            requiredChartSections.forEach(section => {
                if (!this.chartSections[section] || typeof this.chartSections[section].subsections !== 'object') {
                    console.error(`Missing or invalid chart section: ${section}`);
                    valid = false;
                }
            });

            return valid;
        }
    }));
});

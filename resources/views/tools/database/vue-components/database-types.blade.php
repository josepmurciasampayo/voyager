@section('database-types-template')

<div>
    <select :value="column.type.name" @change="onTypeChange" class="form-control" tabindex="-1">
        <optgroup v-for="(types, category) in dbTypes" :label="category">
            <option v-for="type in types" :value="type.name" :disabled="type.notSupported">
                @{{ type.name.toUpperCase() }}
            </option>
        </optgroup>
    </select>
    <div v-if="column.type.notSupported">
        <small>This type is not supported</small>
    </div>
</div>


@endsection

<script>
    let databaseTypes = {!! json_encode($db->types) !!};

    databaseTypes.getType = function (name) {
        let type;
        name = name.toLowerCase().trim();

        for (category in databaseTypes) {
            if (Array.isArray(databaseTypes[category])) {
                type = databaseTypes[category].find(function(type) {
                    return name == type.name.toLowerCase();
                });

                if (type) {
                    return type;
                }
            }
        }

        toastr.error("Unknown type: " + name);

        // fallback to a default type
        return databaseTypes.Numbers[0];
    };

    Vue.component('database-types', {
        props: {
            column: {
                type: Object,
                required: true
            }
        },
        data() {
            return {
                dbTypes: databaseTypes
            };
        },
        template: `@yield('database-types-template')`,
        methods: {
            onTypeChange(event) {
                this.$emit('typeChanged', this.getType(event.target.value));
            },
            getType(name) {
                return this.dbTypes.getType(name);
            }
        }
    });
</script>

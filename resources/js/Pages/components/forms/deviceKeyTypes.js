export const BASE_KEY_TYPES = [
    { value: '', name: 'N/A' },
    { value: 'line', name: 'Line' },
    { value: 'blf', name: 'BLF' },
    { value: 'speed_dial', name: 'Speed Dial' },
    { value: 'check_voicemail', name: 'Check Voicemail' },
    { value: 'park', name: 'Park & Retrieve' },
    { value: 'dtmf', name: 'DTMF' },
]

export const INTRADE_FANVIL_KEY_TYPES = [
    { value: 'voice_mail', name: 'Voice Mail' },
    { value: 'headset', name: 'Headset' },
]

export const KEY_TYPES_WITH_VALUE_SELECT = [
    'line',
    'check_voicemail',
    'blf',
    'speed_dial',
    'park',
]

export const KEY_TYPES_WITHOUT_VALUE_FIELD = [
    'voice_mail',
    'headset',
]

export const KEY_TYPES_WITH_VALUE_TEXT = KEY_TYPES_WITH_VALUE_SELECT.concat(
    KEY_TYPES_WITHOUT_VALUE_FIELD,
)

export function supportsIntradeFanvilKeys(vendor) {
    const value = String(vendor || '').toLowerCase()

    return ['fanvil', 'intrade', 'ibratro'].includes(value)
}

export function getKeyTypes(vendor) {
    if (!supportsIntradeFanvilKeys(vendor)) {
        return [...BASE_KEY_TYPES]
    }

    const types = [...BASE_KEY_TYPES]
    const insertAt = types.findIndex((type) => type.value === 'check_voicemail') + 1

    types.splice(insertAt, 0, ...INTRADE_FANVIL_KEY_TYPES)

    return types
}

export function fixedKeyValue(keyType) {
    if (keyType === 'voice_mail') {
        return 'F_MWI'
    }

    if (keyType === 'headset') {
        return 'F_HEADSET'
    }

    return null
}

export function defaultKeyLabel(keyType) {
    if (keyType === 'voice_mail') {
        return 'Voice Mail'
    }

    if (keyType === 'headset') {
        return 'Headset'
    }

    return null
}

export function resolvedKeyLabel(key) {
    const explicit = key?.key_label
    if (explicit != null && String(explicit).trim() !== '') {
        return String(explicit).trim()
    }

    const generated = key?._generated_label
    if (generated != null && String(generated).trim() !== '') {
        return String(generated).trim()
    }

    return explicit ?? null
}

export function keyLabelDisabledConditions(listName, vendor) {
    if (supportsIntradeFanvilKeys(vendor)) {
        return [[`${listName}.*.key_type`, '']]
    }

    return [[`${listName}.*.key_type`, ['', 'line']]]
}

export function normalizeKeyForSubmit(key, keyTypesWithSelect = KEY_TYPES_WITH_VALUE_SELECT) {
    const keyType = key?.key_type ?? ''
    const fixedValue = fixedKeyValue(keyType)
    let keyLabel = resolvedKeyLabel(key)

    if (keyType === 'line') {
        const generated = key?._generated_label != null
            ? String(key._generated_label).trim()
            : ''
        const explicit = key?.key_label != null
            ? String(key.key_label).trim()
            : ''

        if (explicit === '' || (generated !== '' && explicit === generated)) {
            keyLabel = null
        }
    }

    if (fixedValue) {
        return {
            ...key,
            key_value: fixedValue,
            key_label: keyLabel || defaultKeyLabel(keyType),
        }
    }

    const usesSelect = keyTypesWithSelect.includes(keyType)

    return {
        ...key,
        key_value: usesSelect
            ? (key?.key_value_select ?? key?.key_value ?? null)
            : (key?.key_value_text ?? key?.key_value ?? null),
        key_label: keyLabel,
    }
}

export function applyFixedKeyTypeDefaults(newType, el$, listName, index) {
    const fixedValue = fixedKeyValue(newType)

    if (!fixedValue) {
        return
    }

    const label = defaultKeyLabel(newType)
    el$.form$.el$(`${listName}.${index}.key_value`)?.update(fixedValue)
    el$.form$.el$(`${listName}.${index}.key_label`)?.update(label)
    el$.form$.el$(`${listName}.${index}._generated_label`)?.update(null)
}

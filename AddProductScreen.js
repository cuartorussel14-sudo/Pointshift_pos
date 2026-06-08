import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TextInput,
  TouchableOpacity,
  ScrollView,
  Alert,
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { Picker } from '@react-native-picker/picker';
import DateTimePicker from '@react-native-community/datetimepicker';
import ProductService from '../services/productService';
import CategoryService from '../services/categoryService';
import StoreService from '../services/storeService';
import AuthService from '../services/authService';

export default function AddProductScreen({ route, navigation }) {
  const { barcode } = route.params || {};
  
  const [loading, setLoading] = useState(false);
  const [categories, setCategories] = useState([]);
  const [showDatePicker, setShowDatePicker] = useState(false);
  const [user, setUser] = useState(null);
  const [availableStores, setAvailableStores] = useState([]);
  const [selectedStore, setSelectedStore] = useState(null);

  const [formData, setFormData] = useState({
    name: '',
    barcode: barcode || '',
    category_id: '',
    price: '',
    stock_quantity: '',
    low_stock_threshold: '10',
    expiry: '',
    description: '',
  });

  useEffect(() => {
    loadCategories();
    loadUser();
  }, []);

  const loadCategories = async () => {
    try {
      const response = await CategoryService.getAll();
      if (response.success && response.data) {
        setCategories(response.data);
        // Set first category as default if available
        if (response.data.length > 0) {
          setFormData(prev => ({ ...prev, category_id: String(response.data[0].id) }));
        }
      }
    } catch (error) {
      console.error('Error loading categories:', error);
      Alert.alert('Error', 'Failed to load categories');
    }
  };

  const loadUser = async () => {
    const currentUser = await AuthService.getCurrentUser();
    setUser(currentUser);

    // Load available stores for the user
    if (currentUser?.id) {
      const storesResult = await StoreService.getUserStores(currentUser.id);
      if (storesResult.success) {
        setAvailableStores(storesResult.stores);
        // Set default store based on user role
        if (currentUser.role === 'staff' && storesResult.stores.length > 0) {
          setSelectedStore(storesResult.stores[0]); // Staff default to their store
        } else if (currentUser.role === 'admin' && storesResult.stores.length > 0) {
          setSelectedStore(storesResult.stores[0]); // Admin default to first store
        }
      }
    }
  };

  const handleInputChange = (field, value) => {
    setFormData(prev => ({ ...prev, [field]: value }));
  };

  const onDateChange = (event, selectedDate) => {
    setShowDatePicker(Platform.OS === 'ios');
    if (event.type === 'dismissed') return;
    
    if (selectedDate) {
      const formattedDate = selectedDate.toISOString().split('T')[0];
      setFormData(prev => ({ ...prev, expiry: formattedDate }));
    }
  };

  const validateForm = () => {
    if (!formData.name.trim()) {
      Alert.alert('Validation Error', 'Product name is required');
      return false;
    }
    if (!formData.price || parseFloat(formData.price) <= 0) {
      Alert.alert('Validation Error', 'Please enter a valid price');
      return false;
    }
    if (!formData.stock_quantity || parseInt(formData.stock_quantity) < 0) {
      Alert.alert('Validation Error', 'Please enter a valid stock quantity');
      return false;
    }
    if (!selectedStore) {
      Alert.alert('Validation Error', 'Please select a store');
      return false;
    }
    return true;
  };

  const handleSave = async () => {
    if (!validateForm()) return;

    setLoading(true);
    try {
      const productData = {
        name: formData.name.trim(),
        barcode: formData.barcode.trim() || null,
        category_id: formData.category_id ? parseInt(formData.category_id) : null,
        price: parseFloat(formData.price),
        stock_quantity: parseInt(formData.stock_quantity),
        low_stock_threshold: parseInt(formData.low_stock_threshold || 10),
        expiry: formData.expiry || null,
        description: formData.description.trim(),
        store_id: selectedStore.id,
      };

      const result = await ProductService.addProduct(productData);
      
      setLoading(false);
      
      if (result.success) {
        Alert.alert(
          'Success',
          'Product added successfully!',
          [
            {
              text: 'OK',
              onPress: () => navigation.goBack(),
            },
          ]
        );
      } else {
        Alert.alert('Error', result.message || 'Failed to add product');
      }
    } catch (error) {
      setLoading(false);
      console.error('Error adding product:', error);
      Alert.alert('Error', error.message || 'Failed to add product');
    }
  };

  return (
    <KeyboardAvoidingView
      style={styles.container}
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
    >
      {/* Header */}
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
          <Ionicons name="arrow-back" size={24} color="#fff" />
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Add New Product</Text>
        <View style={{ width: 24 }} />
      </View>

      <ScrollView style={styles.content} keyboardShouldPersistTaps="handled">
        {/* Barcode Info */}
        {barcode && (
          <View style={styles.barcodeInfo}>
            <Ionicons name="barcode-outline" size={24} color="#dc3545" />
            <View style={styles.barcodeTextContainer}>
              <Text style={styles.barcodeLabel}>Scanned Barcode</Text>
              <Text style={styles.barcodeValue}>{barcode}</Text>
            </View>
          </View>
        )}

        {/* Form Fields */}
        <View style={styles.formSection}>
          <Text style={styles.sectionTitle}>Product Information</Text>

          {/* Product Name */}
          <View style={styles.inputGroup}>
            <Text style={styles.label}>
              Product Name <Text style={styles.required}>*</Text>
            </Text>
            <TextInput
              style={styles.input}
              placeholder="Enter product name"
              value={formData.name}
              onChangeText={(value) => handleInputChange('name', value)}
            />
          </View>

          {/* Barcode */}
          <View style={styles.inputGroup}>
            <Text style={styles.label}>Barcode</Text>
            <TextInput
              style={styles.input}
              placeholder="Enter or scan barcode"
              value={formData.barcode}
              onChangeText={(value) => handleInputChange('barcode', value)}
              editable={!barcode}
            />
          </View>

          {/* Store Selection */}
          {availableStores.length > 0 && (
            <View style={styles.inputGroup}>
              <Text style={styles.label}>
                Store <Text style={styles.required}>*</Text>
              </Text>
              <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ marginBottom: 8 }}>
                {availableStores.map((store) => (
                  <TouchableOpacity
                    key={store.id}
                    style={[
                      styles.storeButton,
                      selectedStore?.id === store.id && styles.storeButtonSelected,
                      user?.role === 'staff' && user?.store_id !== store.id && styles.storeButtonDisabled
                    ]}
                    onPress={() => {
                      if (user?.role === 'staff' && user?.store_id !== store.id) {
                        Alert.alert('Access Denied', 'You can only add products to your assigned store.');
                        return;
                      }
                      setSelectedStore(store);
                    }}
                    disabled={user?.role === 'staff' && user?.store_id !== store.id}
                  >
                    <Text style={[
                      styles.storeButtonText,
                      selectedStore?.id === store.id && styles.storeButtonTextSelected,
                      user?.role === 'staff' && user?.store_id !== store.id && styles.storeButtonTextDisabled
                    ]}>
                      {store.name}
                    </Text>
                  </TouchableOpacity>
                ))}
              </ScrollView>
            </View>
          )}

          {/* Category */}
          <View style={styles.inputGroup}>
            <Text style={styles.label}>Category</Text>
            <View style={styles.pickerContainer}>
              <Picker
                selectedValue={formData.category_id}
                onValueChange={(value) => handleInputChange('category_id', value)}
                style={styles.picker}
              >
                <Picker.Item label="Select Category" value="" />
                {categories.map((category) => (
                  <Picker.Item
                    key={category.id}
                    label={category.name}
                    value={String(category.id)}
                  />
                ))}
              </Picker>
            </View>
          </View>

          {/* Price */}
          <View style={styles.inputGroup}>
            <Text style={styles.label}>
              Price (₱) <Text style={styles.required}>*</Text>
            </Text>
            <TextInput
              style={styles.input}
              placeholder="0.00"
              value={formData.price}
              onChangeText={(value) => handleInputChange('price', value)}
              keyboardType="decimal-pad"
            />
          </View>

          {/* Stock Quantity */}
          <View style={styles.inputGroup}>
            <Text style={styles.label}>
              Stock Quantity <Text style={styles.required}>*</Text>
            </Text>
            <TextInput
              style={styles.input}
              placeholder="0"
              value={formData.stock_quantity}
              onChangeText={(value) => handleInputChange('stock_quantity', value)}
              keyboardType="number-pad"
            />
          </View>

          {/* Low Stock Threshold */}
          <View style={styles.inputGroup}>
            <Text style={styles.label}>Low Stock Alert Level</Text>
            <TextInput
              style={styles.input}
              placeholder="10"
              value={formData.low_stock_threshold}
              onChangeText={(value) => handleInputChange('low_stock_threshold', value)}
              keyboardType="number-pad"
            />
          </View>

          {/* Expiry Date */}
          <View style={styles.inputGroup}>
            <Text style={styles.label}>Expiry Date (Optional)</Text>
            <TouchableOpacity
              style={styles.dateButton}
              onPress={() => setShowDatePicker(true)}
            >
              <Ionicons name="calendar-outline" size={20} color="#666" />
              <Text style={styles.dateButtonText}>
                {formData.expiry || 'Select expiry date'}
              </Text>
            </TouchableOpacity>
            {formData.expiry && (
              <TouchableOpacity
                style={styles.clearDateButton}
                onPress={() => handleInputChange('expiry', '')}
              >
                <Text style={styles.clearDateText}>Clear Date</Text>
              </TouchableOpacity>
            )}
          </View>

          {showDatePicker && (
            <DateTimePicker
              value={formData.expiry ? new Date(formData.expiry) : new Date()}
              mode="date"
              display={Platform.OS === 'ios' ? 'spinner' : 'default'}
              onChange={onDateChange}
              minimumDate={new Date()}
            />
          )}

          {/* Description */}
          <View style={styles.inputGroup}>
            <Text style={styles.label}>Description (Optional)</Text>
            <TextInput
              style={[styles.input, styles.textArea]}
              placeholder="Enter product description"
              value={formData.description}
              onChangeText={(value) => handleInputChange('description', value)}
              multiline
              numberOfLines={4}
              textAlignVertical="top"
            />
          </View>
        </View>

        {/* Save Button */}
        <TouchableOpacity
          style={[styles.saveButton, loading && styles.saveButtonDisabled]}
          onPress={handleSave}
          disabled={loading}
        >
          {loading ? (
            <ActivityIndicator color="#fff" />
          ) : (
            <>
              <Ionicons name="checkmark-circle-outline" size={24} color="#fff" />
              <Text style={styles.saveButtonText}>Save Product</Text>
            </>
          )}
        </TouchableOpacity>

        <View style={{ height: 30 }} />
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f5f5f5',
  },
  header: {
    backgroundColor: '#dc3545',
    paddingTop: 50,
    paddingBottom: 15,
    paddingHorizontal: 20,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  backButton: {
    padding: 5,
  },
  headerTitle: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#fff',
  },
  content: {
    flex: 1,
  },
  barcodeInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#fff',
    margin: 15,
    padding: 15,
    borderRadius: 10,
    borderLeftWidth: 4,
    borderLeftColor: '#dc3545',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  barcodeTextContainer: {
    marginLeft: 15,
    flex: 1,
  },
  barcodeLabel: {
    fontSize: 12,
    color: '#666',
    marginBottom: 4,
  },
  barcodeValue: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#333',
  },
  formSection: {
    backgroundColor: '#fff',
    margin: 15,
    marginTop: 0,
    padding: 20,
    borderRadius: 10,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#333',
    marginBottom: 20,
  },
  inputGroup: {
    marginBottom: 20,
  },
  label: {
    fontSize: 14,
    fontWeight: '600',
    color: '#333',
    marginBottom: 8,
  },
  required: {
    color: '#dc3545',
  },
  input: {
    backgroundColor: '#f8f9fa',
    borderWidth: 1,
    borderColor: '#e0e0e0',
    borderRadius: 8,
    padding: 12,
    fontSize: 16,
    color: '#333',
  },
  textArea: {
    height: 100,
    paddingTop: 12,
  },
  pickerContainer: {
    backgroundColor: '#f8f9fa',
    borderWidth: 1,
    borderColor: '#e0e0e0',
    borderRadius: 8,
    overflow: 'hidden',
  },
  picker: {
    height: 50,
  },
  dateButton: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#f8f9fa',
    borderWidth: 1,
    borderColor: '#e0e0e0',
    borderRadius: 8,
    padding: 12,
  },
  dateButtonText: {
    fontSize: 16,
    color: '#333',
    marginLeft: 10,
  },
  clearDateButton: {
    marginTop: 8,
    alignSelf: 'flex-start',
  },
  clearDateText: {
    color: '#dc3545',
    fontSize: 14,
    fontWeight: '600',
  },
  saveButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#28a745',
    marginHorizontal: 15,
    padding: 15,
    borderRadius: 10,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.2,
    shadowRadius: 4,
    elevation: 3,
  },
  saveButtonDisabled: {
    backgroundColor: '#999',
  },
  saveButtonText: {
    color: '#fff',
    fontSize: 18,
    fontWeight: 'bold',
    marginLeft: 10,
  },
  storeButton: {
    backgroundColor: '#f0f0f0',
    paddingHorizontal: 15,
    paddingVertical: 8,
    borderRadius: 20,
    marginRight: 10,
    borderWidth: 1,
    borderColor: '#ddd',
  },
  storeButtonSelected: {
    backgroundColor: '#dc3545',
    borderColor: '#dc3545',
  },
  storeButtonText: {
    color: '#666',
    fontSize: 14,
    fontWeight: '600',
  },
  storeButtonTextSelected: {
    color: '#fff',
  },
  storeButtonDisabled: {
    backgroundColor: '#f5f5f5',
    borderColor: '#ccc',
    opacity: 0.6,
  },
  storeButtonTextDisabled: {
    color: '#999',
  },
});

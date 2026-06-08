import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  Alert,
  ScrollView,
  ActivityIndicator,
  Vibration,
  TextInput,
  Platform,
} from 'react-native';
import { Linking } from 'react-native';
import DateTimePicker from '@react-native-community/datetimepicker';
import { CameraView, Camera } from 'expo-camera';
import { StatusBar } from 'expo-status-bar';
import ProductService from '../services/productService';
import AuthService from '../services/authService';
import StoreService from '../services/storeService';

export default function ScannerScreen({ navigation }) {
  const [hasPermission, setHasPermission] = useState(null);
  const [scanned, setScanned] = useState(false);
  const [product, setProduct] = useState(null);
  const [loading, setLoading] = useState(false);
  const [addQty, setAddQty] = useState('1');
  const [addPrice, setAddPrice] = useState('');
  const [expiry, setExpiry] = useState(null);
  const [showDatePicker, setShowDatePicker] = useState(false);
  const [user, setUser] = useState(null);
  const [availableStores, setAvailableStores] = useState([]);
  const [selectedStore, setSelectedStore] = useState(null);

  useEffect(() => {
    getCameraPermission();
    loadUser();
  }, []);

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

  const getCameraPermission = async () => {
    const { status } = await Camera.requestCameraPermissionsAsync();
    setHasPermission(status === 'granted');
  };

  const handleBarCodeScanned = async ({ type, data }) => {
    if (scanned) return;

    setScanned(true);
    Vibration.vibrate(100);
    setLoading(true);

    const result = await ProductService.getProductByBarcode(data);
    setLoading(false);

    if (result.success) {
      setProduct(result.product);
    } else {
      // Product not found - offer to add new product
      Alert.alert(
        'Product Not Found',
        `Barcode "${data}" is not in the system. Would you like to add this product?`,
        [
          { 
            text: 'Cancel', 
            style: 'cancel',
            onPress: () => setScanned(false) 
          },
          {
            text: 'Add Product',
            onPress: () => {
              setScanned(false);
              navigation.navigate('AddProduct', { barcode: data });
            },
          },
        ]
      );
    }
  };

  const handleScanAgain = () => {
    setScanned(false);
    setProduct(null);
    setAddQty('1');
    setAddPrice('');
    setExpiry(null);
  };

  const onChangeExpiry = (event, selectedDate) => {
    setShowDatePicker(Platform.OS === 'ios');
    if (event.type === 'dismissed') return;
    const currentDate = selectedDate || expiry;
    setExpiry(currentDate);
  };

  const formatDate = (d) => {
    if (!d) return '';
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
  };

  const handleAddToPOS = async () => {
    if (!product || !user) return;

    setLoading(true);
    try {
      const result = await ProductService.sendToPOS(product, user.id);
      setLoading(false);

      if (result.success) {
        Alert.alert('Sent to POS', `${product.name} was sent to your POS terminal.`);
      } else {
        Alert.alert('Failed to Send', result.message || 'Could not send product to POS.');
      }
    } catch (error) {
      setLoading(false);
      console.error('handleAddToPOS error', error);
      Alert.alert(
        'Connection Error',
        'Could not connect to the POS server. Please check your network connection.'
      );
    }
  };


  const handleAddStock = async () => {
    if (!product) return;

    const qtyToAdd = parseInt(addQty, 10) || 0;
    if (qtyToAdd <= 0) {
      Alert.alert('Invalid quantity', 'Please enter a quantity greater than zero.');
      return;
    }

    if (!selectedStore) {
      Alert.alert('Store Required', 'Please select a store before adding stock.');
      return;
    }

    const currentStock = parseInt(product.stock_quantity || 0, 10);
    const newStock = currentStock + qtyToAdd;

    setLoading(true);
    try {
      const payload = {
        name: product.name || '',
        price: addPrice ? parseFloat(addPrice) : parseFloat(product.price) || 0,
        stock_quantity: newStock,
        barcode: product.barcode || null,
        expiry: expiry ? formatDate(expiry) : null,
        description: product.description || '',
        user_id: user?.id || null,
        store_id: selectedStore.id,
      };

      const res = await ProductService.updateProduct(product.id, payload);

      setLoading(false);
      if (res && res.success) {
        Alert.alert('Stock updated', `Added ${qtyToAdd} units to ${selectedStore.name}. New stock: ${newStock}${addPrice ? `. Price updated to ₱${addPrice}` : ''}`, [
          { text: 'OK', onPress: () => {
            // update local product state
            setProduct({ ...product, stock_quantity: newStock, price: payload.price, expiry: payload.expiry });
            setScanned(false);
          } }
        ]);
      } else {
        Alert.alert('Update failed', res.message || 'Failed to update product');
      }
    } catch (err) {
      setLoading(false);
      console.error('Update stock error', err);
      Alert.alert('Error', 'Network error while updating stock');
    }
  };

  const handleLogout = async () => {
    Alert.alert('Logout', 'Are you sure you want to logout?', [
      { text: 'Cancel', style: 'cancel' },
      {
        text: 'Logout',
        style: 'destructive',
        onPress: async () => {
          await AuthService.logout();
          navigation.replace('Login');
        },
      },
    ]);
  };

  if (hasPermission === null) {
    return (
      <View style={styles.container}>
        <ActivityIndicator size="large" color="#dc3545" />
        <Text style={styles.loadingText}>Requesting camera permission...</Text>
      </View>
    );
  }

  if (hasPermission === false) {
    return (
      <View style={styles.container}>
        <Text style={styles.errorText}>No access to camera</Text>
        <TouchableOpacity style={styles.button} onPress={getCameraPermission}>
          <Text style={styles.buttonText}>Grant Permission</Text>
        </TouchableOpacity>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <StatusBar style="light" />

      {/* Header */}
      <View style={styles.header}>
        <View>
          <Text style={styles.headerTitle}>PointShift Scanner</Text>
          <Text style={styles.headerSubtitle}>
            {user ? `Welcome, ${user.first_name}!` : 'Scan Product Barcode'}
          </Text>
        </View>
        <TouchableOpacity style={styles.logoutButton} onPress={handleLogout}>
          <Text style={styles.logoutText}>Logout</Text>
        </TouchableOpacity>
      </View>

      {/* Camera View */}
      {!scanned && (
        <View style={styles.cameraContainer}>
          <CameraView
            style={styles.camera}
            onBarcodeScanned={scanned ? undefined : handleBarCodeScanned}
            barcodeScannerSettings={{
              barcodeTypes: ['qr', 'ean13', 'ean8', 'code128', 'code39', 'upc_a', 'upc_e'],
            }}
          />
          <View style={styles.scannerOverlay}>
            <View style={styles.scannerFrame} />
            <Text style={styles.scannerText}>
              Position barcode within the frame
            </Text>
          </View>
        </View>
      )}

      {/* Loading */}
      {loading && (
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#dc3545" />
          <Text style={styles.loadingText}>Fetching product...</Text>
        </View>
      )}

      {/* Product Details */}
      {product && scanned && (
        <ScrollView style={styles.productContainer}>
          <View style={styles.productCard}>
            <View style={styles.productHeader}>
              <Text style={styles.productName}>{product.name}</Text>
              {product.is_low_stock && (
                <View style={styles.lowStockBadge}>
                  <Text style={styles.lowStockText}>Low Stock</Text>
                </View>
              )}
            </View>

            <View style={styles.productDetails}>
              <View style={styles.detailRow}>
                <Text style={styles.detailLabel}>SKU:</Text>
                <Text style={styles.detailValue}>{product.sku || 'N/A'}</Text>
              </View>

              <View style={styles.detailRow}>
                <Text style={styles.detailLabel}>Barcode:</Text>
                <Text style={styles.detailValue}>{product.barcode}</Text>
              </View>

              <View style={styles.detailRow}>
                <Text style={styles.detailLabel}>Category:</Text>
                <Text style={styles.detailValue}>{product.category_name || 'N/A'}</Text>
              </View>

              <View style={styles.detailRow}>
                <Text style={styles.detailLabel}>Price:</Text>
                <Text style={styles.priceValue}>₱{product.price}</Text>
              </View>

              <View style={styles.detailRow}>
                <Text style={styles.detailLabel}>Stock:</Text>
                <Text style={[styles.detailValue, product.stock_quantity <= product.low_stock_threshold && styles.lowStockValue]}>
                  {product.stock_quantity} units
                </Text>
              </View>

              {product.description && (
                <View style={styles.descriptionContainer}>
                  <Text style={styles.detailLabel}>Description:</Text>
                  <Text style={styles.descriptionText}>{product.description}</Text>
                </View>
              )}
            </View>

              {/* Add stock controls */}
              <View style={{ marginTop: 10 }}>
                <Text style={[styles.detailLabel, { marginBottom: 8 }]}>Add Stock</Text>

                {/* Store Selection */}
                {availableStores.length > 0 && (
                  <View style={{ marginBottom: 10 }}>
                    <Text style={[styles.detailLabel, { marginBottom: 5 }]}>Select Store *</Text>
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
                              Alert.alert('Access Denied', 'You can only add stock to your assigned store.');
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

                <View style={{ marginBottom: 8 }}>
                  <Text style={[styles.detailLabel, { marginBottom: 5 }]}>Quantity *</Text>
                  <TextInput
                    value={String(addQty)}
                    onChangeText={setAddQty}
                    keyboardType="number-pad"
                    placeholder="Enter quantity"
                    style={{ backgroundColor: '#f0f0f0', padding: 10, borderRadius: 8, marginBottom: 8 }}
                  />
                </View>

                <View style={{ marginBottom: 8 }}>
                  <Text style={[styles.detailLabel, { marginBottom: 5 }]}>New Price (optional)</Text>
                  <TextInput
                    value={addPrice}
                    onChangeText={setAddPrice}
                    keyboardType="decimal-pad"
                    placeholder="Enter new price"
                    style={{ backgroundColor: '#f0f0f0', padding: 10, borderRadius: 8, marginBottom: 8 }}
                  />
                </View>

                <TouchableOpacity style={[styles.button, { paddingVertical: 10, paddingHorizontal: 12, marginBottom: 8 }]} onPress={() => setShowDatePicker(true)}>
                  <Text style={styles.buttonText}>{expiry ? formatDate(expiry) : 'Set expiry (optional)'}</Text>
                </TouchableOpacity>

                {showDatePicker && (
                  <DateTimePicker
                    value={expiry || new Date()}
                    mode="date"
                    display={Platform.OS === 'ios' ? 'spinner' : 'default'}
                    onChange={onChangeExpiry}
                  />
                )}

                <TouchableOpacity style={[styles.addButton, { marginTop: 6 }]} onPress={handleAddStock}>
                  <Text style={styles.addButtonText}>Add Stock</Text>
                </TouchableOpacity>
              </View>

              {/* Add to POS button */}
              <TouchableOpacity style={[styles.addButton, { marginTop: 10, backgroundColor: '#0d6efd' }]} onPress={handleAddToPOS} disabled={loading}>
                <Text style={styles.addButtonText}>Add to POS</Text>
              </TouchableOpacity>

              <TouchableOpacity style={styles.scanButton} onPress={handleScanAgain}>
                <Text style={styles.scanButtonText}>Scan Another Product</Text>
              </TouchableOpacity>
          </View>
        </ScrollView>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f5f5f5',
  },
  header: {
    backgroundColor: '#dc3545',
    padding: 20,
    paddingTop: 50,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  headerTitle: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#fff',
  },
  headerSubtitle: {
    fontSize: 14,
    color: '#fff',
    opacity: 0.9,
    marginTop: 4,
  },
  logoutButton: {
    backgroundColor: '#fff',
    paddingHorizontal: 15,
    paddingVertical: 8,
    borderRadius: 5,
  },
  logoutText: {
    color: '#dc3545',
    fontWeight: 'bold',
  },
  cameraContainer: {
    flex: 1,
    position: 'relative',
  },
  camera: {
    flex: 1,
  },
  scannerOverlay: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    justifyContent: 'center',
    alignItems: 'center',
  },
  scannerFrame: {
    width: 250,
    height: 250,
    borderWidth: 3,
    borderColor: '#fff',
    borderRadius: 10,
    backgroundColor: 'transparent',
  },
  scannerText: {
    color: '#fff',
    fontSize: 16,
    marginTop: 20,
    textAlign: 'center',
    backgroundColor: 'rgba(0,0,0,0.5)',
    padding: 10,
    borderRadius: 5,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#fff',
  },
  loadingText: {
    marginTop: 15,
    fontSize: 16,
    color: '#666',
  },
  productContainer: {
    flex: 1,
    padding: 20,
  },
  productCard: {
    backgroundColor: '#fff',
    borderRadius: 15,
    padding: 20,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 5,
    elevation: 3,
  },
  productHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 20,
    borderBottomWidth: 1,
    borderBottomColor: '#eee',
    paddingBottom: 15,
  },
  productName: {
    fontSize: 22,
    fontWeight: 'bold',
    color: '#333',
    flex: 1,
  },
  lowStockBadge: {
    backgroundColor: '#dc3545',
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 5,
  },
  lowStockText: {
    color: '#fff',
    fontSize: 12,
    fontWeight: 'bold',
  },
  productDetails: {
    marginBottom: 20,
  },
  detailRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 12,
  },
  detailLabel: {
    fontSize: 16,
    color: '#666',
    fontWeight: '600',
  },
  detailValue: {
    fontSize: 16,
    color: '#333',
  },
  priceValue: {
    fontSize: 20,
    color: '#28a745',
    fontWeight: 'bold',
  },
  lowStockValue: {
    color: '#dc3545',
    fontWeight: 'bold',
  },
  descriptionContainer: {
    marginTop: 10,
    paddingTop: 15,
    borderTopWidth: 1,
    borderTopColor: '#eee',
  },
  descriptionText: {
    fontSize: 14,
    color: '#666',
    marginTop: 5,
    lineHeight: 20,
  },
  scanButton: {
    backgroundColor: '#dc3545',
    padding: 15,
    borderRadius: 10,
    alignItems: 'center',
    marginTop: 10,
  },
  scanButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: 'bold',
  },
  addButton: { backgroundColor: '#28a745', padding: 12, borderRadius: 8, alignItems: 'center' },
  addButtonText: { color: '#fff', fontWeight: 'bold' },
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
  errorText: {
    fontSize: 18,
    color: '#dc3545',
    textAlign: 'center',
    marginBottom: 20,
  },
  button: {
    backgroundColor: '#dc3545',
    padding: 15,
    borderRadius: 10,
    marginHorizontal: 20,
  },
  buttonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: 'bold',
    textAlign: 'center',
  },
});

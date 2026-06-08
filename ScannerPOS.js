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
} from 'react-native';
import { CameraView, Camera } from 'expo-camera';
import { StatusBar } from 'expo-status-bar';
import ProductService from '../services/productService';
import AuthService from '../services/authService';
import { API_ENDPOINTS, API_KEYS } from '../config/api'; // Ensure this is correctly configured

export default function ScannerPOS({ navigation }) {
  const [hasPermission, setHasPermission] = useState(null);
  const [scanned, setScanned] = useState(false);
  const [product, setProduct] = useState(null);
  const [loading, setLoading] = useState(false);
  const [user, setUser] = useState(null);

  useEffect(() => {
    getCameraPermission();
    loadUser(); // Load user on component mount
  }, []);

  const loadUser = async () => {
    const currentUser = await AuthService.getCurrentUser();
    console.log('Loaded user:', JSON.stringify(currentUser, null, 2));
    setUser(currentUser);
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
      // Product loaded — wait for user to press Add to POS to publish to web POS
    } else {
      Alert.alert('Product Not Found', result.message, [
        { text: 'OK', onPress: () => setScanned(false) },
      ]);
    }
  };

  const handleAddToCart = async () => {
    if (!product) return;

    console.log('Current user state:', JSON.stringify(user, null, 2));
    
    // Ensure we have a user ID before sending
    if (!user || !user.id) {
      Alert.alert('Authentication Error', `Could not identify the current user. Please log out and log back in.\n\nUser data: ${JSON.stringify(user)}`);
      return;
    }

    const msgId = `mobile_${Date.now()}_${Math.random().toString(16).slice(2,10)}`;
    const payload = {
      msgId: msgId,
      product: {
        id: product.id ?? product.product_id ?? product.sku ?? product.barcode,
        name: product.name || product.title || 'Item',
        price: Number(product.price) || 0,
        stock_quantity: product.stock_quantity || 0,
        barcode: product.barcode || null,
        qty: 1,
        expiry: product.expiry || null,
      },
      user_id: user.id, // Explicitly send the logged-in user's ID
      cashier_id: user.id, // Route to this cashier only
      channel: `pos_cashier_${user.id}`,
    };

    setLoading(true);
    console.log('Sending to POS:', JSON.stringify(payload, null, 2));
    console.log('Endpoint:', API_ENDPOINTS.PUBLISH_POS_MESSAGE);
    console.log('API Key (first 20 chars):', API_KEYS.PRODUCT_LOOKUP_API_KEY.substring(0, 20));
    
    try {
      const res = await fetch(API_ENDPOINTS.PUBLISH_POS_MESSAGE, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-API-KEY': API_KEYS.PRODUCT_LOOKUP_API_KEY,
        },
        body: JSON.stringify(payload),
      });
      
      console.log('Response status:', res.status);
      const responseText = await res.text();
      console.log('Response text:', responseText);
      
      let data;
      try {
        data = JSON.parse(responseText);
      } catch (parseErr) {
        console.error('Failed to parse response:', parseErr);
        setLoading(false);
        Alert.alert('Server Error', `Invalid response from server: ${responseText.substring(0, 100)}`);
        return;
      }
      
      setLoading(false);
      console.log('Response data:', JSON.stringify(data, null, 2));
      
      if (data && data.success) {
        Alert.alert('Added to POS', `${product.name} sent to web POS.`, [
          { text: 'Scan Another', onPress: () => handleScanAgain() },
        ]);
        return;
      }
      
      const errorMsg = data?.error || data?.message || 'Failed to send to POS';
      const debugInfo = data?.debug ? `\n\nDebug: ${JSON.stringify(data.debug)}` : '';
      Alert.alert('Publish failed', errorMsg + debugInfo);
    } catch (err) {
      setLoading(false);
      console.error('Publish POS message failed:', err);
      Alert.alert('Network error', `Failed to send to web POS.\n\nError: ${err.message}`);
    }
  };

  const handleScanAgain = () => {
    setScanned(false);
    setProduct(null);
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

      <View style={styles.header}>
        <Text style={styles.headerTitle}>POS Scanner</Text>
      </View>

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
            <Text style={styles.scannerText}>Scan product to add to cart</Text>
          </View>
        </View>
      )}

      {loading && (
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#dc3545" />
          <Text style={styles.loadingText}>Fetching product...</Text>
        </View>
      )}

      {product && scanned && (
        <ScrollView style={styles.productContainer}>
          <View style={styles.productCard}>
            <Text style={styles.productName}>{product.name}</Text>
            <Text style={styles.detailLabel}>Price: ₱{product.price}</Text>
            <Text style={styles.detailLabel}>Stock: {product.stock_quantity}</Text>

            <TouchableOpacity style={styles.addButton} onPress={handleAddToCart}>
              <Text style={styles.addButtonText}>Add to Cart</Text>
            </TouchableOpacity>

            <TouchableOpacity style={styles.scanButton} onPress={handleScanAgain}>
              <Text style={styles.scanButtonText}>Scan Another</Text>
            </TouchableOpacity>
          </View>
        </ScrollView>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f5f5f5' },
  header: { backgroundColor: '#dc3545', padding: 20, paddingTop: 50, flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  headerTitle: { color: '#fff', fontSize: 20, fontWeight: 'bold' },
  cartButton: { backgroundColor: '#fff', paddingHorizontal: 12, paddingVertical: 8, borderRadius: 6 },
  cartText: { color: '#dc3545', fontWeight: 'bold' },
  cameraContainer: { flex: 1, position: 'relative' },
  camera: { flex: 1 },
  scannerOverlay: { position: 'absolute', top: 0, left: 0, right: 0, bottom: 0, justifyContent: 'center', alignItems: 'center' },
  scannerFrame: { width: 250, height: 250, borderWidth: 3, borderColor: '#fff', borderRadius: 10 },
  scannerText: { color: '#fff', fontSize: 16, marginTop: 20, textAlign: 'center', backgroundColor: 'rgba(0,0,0,0.5)', padding: 10, borderRadius: 5 },
  loadingContainer: { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: '#fff' },
  loadingText: { marginTop: 15, fontSize: 16, color: '#666' },
  productContainer: { flex: 1, padding: 20 },
  productCard: { backgroundColor: '#fff', borderRadius: 15, padding: 20 },
  productName: { fontSize: 20, fontWeight: 'bold', marginBottom: 10 },
  detailLabel: { fontSize: 16, color: '#666', marginBottom: 6 },
  addButton: { backgroundColor: '#28a745', padding: 12, borderRadius: 8, marginTop: 12, alignItems: 'center' },
  addButtonText: { color: '#fff', fontWeight: 'bold' },
  scanButton: { backgroundColor: '#dc3545', padding: 12, borderRadius: 8, marginTop: 12, alignItems: 'center' },
  scanButtonText: { color: '#fff', fontWeight: 'bold' },
  button: { backgroundColor: '#dc3545', padding: 15, borderRadius: 10, marginHorizontal: 20 },
  buttonText: { color: '#fff', fontSize: 16, fontWeight: 'bold', textAlign: 'center' },
  errorText: { fontSize: 18, color: '#dc3545', textAlign: 'center', marginBottom: 20 },
});
